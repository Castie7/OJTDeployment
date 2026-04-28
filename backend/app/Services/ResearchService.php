<?php

namespace App\Services;

use App\Models\ResearchModel;
use App\Models\ResearchDetailsModel;
use App\Models\ResearchCommentModel;
use App\Models\NotificationModel;
use App\Models\ResearchIndexJobModel;
use App\Models\UserModel;

class ResearchService extends BaseService
{
    private const DEFAULT_ACCESS_LEVEL = 'private';
    private const MAX_INDEX_TEXT_CHARS = 120000;
    private const MAX_FALLBACK_PDF_BYTES = 8388608; // 8 MB
    private const OCR_MIN_TRIGGER_TEXT_CHARS = 120;

    protected $researchModel;
    protected $detailsModel;
    protected $commentModel;
    protected $notifModel;
    protected $indexJobModel;
    protected $userModel;
    private ?bool $hasAccessLevelColumn = null;
    private ?bool $hasSearchTextColumn = null;
    private ?bool $hasViewCountColumn = null;
    private ?bool $hasPdftoppmBinary = null;
    private ?bool $hasTesseractBinary = null;
    private ?bool $hasResearchesFullTextIndex = null;
    private ?bool $hasDetailsFullTextIndex = null;
    private ?array $researchesFullTextIndexColumns = null;
    private ?array $detailsFullTextIndexColumns = null;

    // Helper select string
    private $selectString = 'researches.*, 
                             research_details.knowledge_type, 
                             research_details.publication_date, 
                             research_details.edition, 
                             research_details.publisher, 
                             research_details.physical_description, 
                             research_details.isbn_issn, 
                             research_details.subjects, 
                             research_details.shelf_location, 
                             research_details.item_condition, 
                             research_details.link';

    public function __construct()
    {
        parent::__construct();
        $this->researchModel = new ResearchModel();
        $this->detailsModel = new ResearchDetailsModel();
        $this->commentModel = new ResearchCommentModel();
        $this->notifModel = new NotificationModel();
        $this->indexJobModel = new ResearchIndexJobModel();
        $this->userModel = new UserModel();
    }

    private function getResearchStoragePath(?string $fileName): ?string
    {
        $normalized = trim((string) $fileName);
        if ($normalized === '') {
            return null;
        }

        return WRITEPATH . 'uploads/research/' . basename($normalized);
    }

    private function stageResearchFileDeletion(?string $fileName): array
    {
        $originalPath = $this->getResearchStoragePath($fileName);
        if ($originalPath === null || !is_file($originalPath)) {
            return [null, null];
        }

        $stagedPath = $originalPath . '.pending-delete-' . str_replace('.', '-', uniqid('', true));
        if (!@rename($originalPath, $stagedPath)) {
            throw new \RuntimeException('Unable to prepare the stored PDF for deletion. Please close the file and try again.');
        }

        return [$originalPath, $stagedPath];
    }

    private function restoreStagedResearchFile(?string $originalPath, ?string $stagedPath): void
    {
        if ($originalPath === null || $stagedPath === null || !is_file($stagedPath)) {
            return;
        }

        if (!@rename($stagedPath, $originalPath)) {
            log_message('critical', '[Research Delete] Failed to restore staged file: ' . $stagedPath);
        }
    }

    private function finalizeStagedResearchFileDeletion(?string $stagedPath): void
    {
        if ($stagedPath === null || !is_file($stagedPath)) {
            return;
        }

        if (!@unlink($stagedPath)) {
            log_message('warning', '[Research Delete] Failed to remove staged file from disk: ' . $stagedPath);
        }
    }

    private function hasAccessLevelColumn(): bool
    {
        if ($this->hasAccessLevelColumn !== null) {
            return $this->hasAccessLevelColumn;
        }

        $this->hasAccessLevelColumn = $this->db->fieldExists('access_level', 'researches');

        return $this->hasAccessLevelColumn;
    }

    private function hasSearchTextColumn(): bool
    {
        if ($this->hasSearchTextColumn !== null) {
            return $this->hasSearchTextColumn;
        }

        $this->hasSearchTextColumn = $this->db->fieldExists('search_text', 'research_details');

        return $this->hasSearchTextColumn;
    }

    private function hasViewCountColumn(): bool
    {
        if ($this->hasViewCountColumn !== null) {
            return $this->hasViewCountColumn;
        }

        $this->hasViewCountColumn = $this->db->fieldExists('view_count', 'researches');

        return $this->hasViewCountColumn;
    }

    private function getIndexColumns(string $table, string $indexName): array
    {
        $rows = $this->db->table('INFORMATION_SCHEMA.STATISTICS')
            ->select('COLUMN_NAME, SEQ_IN_INDEX')
            ->where('TABLE_SCHEMA', $this->db->getDatabase())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->orderBy('SEQ_IN_INDEX', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $column = strtolower(trim((string) ($row['COLUMN_NAME'] ?? '')));
            if ($column !== '' && preg_match('/^[a-z0-9_]+$/', $column)) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    private function getResearchesFullTextIndexColumns(): array
    {
        if ($this->researchesFullTextIndexColumns !== null) {
            return $this->researchesFullTextIndexColumns;
        }

        $this->researchesFullTextIndexColumns = $this->getIndexColumns('researches', 'ft_researches_title_author');

        return $this->researchesFullTextIndexColumns;
    }

    private function getDetailsFullTextIndexColumns(): array
    {
        if ($this->detailsFullTextIndexColumns !== null) {
            return $this->detailsFullTextIndexColumns;
        }

        $this->detailsFullTextIndexColumns = $this->getIndexColumns('research_details', 'ft_research_details_search');

        return $this->detailsFullTextIndexColumns;
    }

    private function hasResearchesFullTextIndex(): bool
    {
        if ($this->hasResearchesFullTextIndex !== null) {
            return $this->hasResearchesFullTextIndex;
        }

        $this->hasResearchesFullTextIndex = !empty($this->getResearchesFullTextIndexColumns());

        return $this->hasResearchesFullTextIndex;
    }

    private function hasDetailsFullTextIndex(): bool
    {
        if ($this->hasDetailsFullTextIndex !== null) {
            return $this->hasDetailsFullTextIndex;
        }

        $this->hasDetailsFullTextIndex = !empty($this->getDetailsFullTextIndexColumns());

        return $this->hasDetailsFullTextIndex;
    }

    private function normalizeAccessLevel(?string $accessLevel): string
    {
        $normalized = strtolower(trim((string) $accessLevel));

        if (in_array($normalized, ['public', 'private'], true)) {
            return $normalized;
        }

        return self::DEFAULT_ACCESS_LEVEL;
    }

    private function commandExists(string $command): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $checkCommand = $isWindows
            ? "where {$command} 2>NUL"
            : "command -v {$command} 2>/dev/null";

        $output = @shell_exec($checkCommand);

        return is_string($output) && trim($output) !== '';
    }

    private function hasPdftoppmBinary(): bool
    {
        if ($this->hasPdftoppmBinary !== null) {
            return $this->hasPdftoppmBinary;
        }

        $this->hasPdftoppmBinary = $this->commandExists('pdftoppm');

        return $this->hasPdftoppmBinary;
    }

    private function hasTesseractBinary(): bool
    {
        if ($this->hasTesseractBinary !== null) {
            return $this->hasTesseractBinary;
        }

        $this->hasTesseractBinary = $this->commandExists('tesseract');

        return $this->hasTesseractBinary;
    }

    private function getOcrMaxPages(): int
    {
        $value = (int) env('search.ocr.maxPages', 3);
        if ($value < 1) {
            return 1;
        }
        if ($value > 12) {
            return 12;
        }
        return $value;
    }

    private function getOcrDpi(): int
    {
        $value = (int) env('search.ocr.dpi', 180);
        if ($value < 120) {
            return 120;
        }
        if ($value > 300) {
            return 300;
        }
        return $value;
    }

    private function getOcrLanguage(): string
    {
        $language = trim((string) env('search.ocr.lang', 'eng'));
        if ($language === '') {
            return 'eng';
        }

        if (!preg_match('/^[a-zA-Z+_]+$/', $language)) {
            return 'eng';
        }

        return strtolower($language);
    }

    private function tokenizeSearchQuery(string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $tokens = array_map(static function ($token): string {
            $clean = preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string) $token);
            return mb_strtolower(trim((string) $clean));
        }, $tokens);
        $tokens = array_values(array_unique(array_filter(
            $tokens,
            static fn ($token) => $token !== '' && mb_strlen((string) $token) >= 3
        )));

        return array_slice($tokens, 0, 8);
    }

    private function sanitizeBooleanToken(string $token): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $token) ?? $token;
        return mb_strtolower(trim($clean));
    }

    private function sanitizeBooleanPhrase(string $phrase): string
    {
        $clean = trim($phrase);
        $clean = str_replace(['"', "'", '+', '-', '<', '>', '(', ')', '~', '*', '@'], ' ', $clean);
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        return trim($clean);
    }

    private function applySpellingCorrections(string $query): string
    {
        $typoMap = [
            'sweeet' => 'sweet',
            'potatto' => 'potato',
            'pototo' => 'potato',
            'camotte' => 'camote',
            'cassavaa' => 'cassava',
            'yucca' => 'yuca',
            'blite' => 'blight',
            'managment' => 'management',
            'nutriton' => 'nutrition',
            'reserch' => 'research',
            'journel' => 'journal',
            'pubisher' => 'publisher',
            'isnb' => 'isbn',
        ];

        return preg_replace_callback('/\b[[:alnum:]\-]{3,}\b/u', static function ($matches) use ($typoMap) {
            $word = strtolower((string) $matches[0]);
            return $typoMap[$word] ?? $matches[0];
        }, $query) ?? $query;
    }

    private function expandSearchQuery(string $query): string
    {
        $corrected = $this->applySpellingCorrections($query);
        $normalized = mb_strtolower($corrected);

        $synonymMap = [
            'sweet potato' => ['camote', 'ipomoea batatas'],
            'camote' => ['sweet potato'],
            'cassava' => ['yuca', 'manioc', 'manihot esculenta'],
            'yuca' => ['cassava'],
            'disease' => ['blight', 'pathogen', 'infection'],
            'nutrition' => ['nutritional', 'nutrient'],
            'journal' => ['article', 'paper'],
            'thesis' => ['dissertation'],
            'isbn' => ['issn'],
        ];

        $extraTerms = [];
        foreach ($synonymMap as $source => $targets) {
            if (str_contains($normalized, $source)) {
                foreach ($targets as $target) {
                    $extraTerms[] = $target;
                }
            }
        }

        if (empty($extraTerms)) {
            return $corrected;
        }

        $extraTerms = array_values(array_unique($extraTerms));
        return trim($corrected . ' ' . implode(' ', $extraTerms));
    }

    private function hasPromptInjectionPattern(string $value): bool
    {
        $patterns = [
            '/ignore\s+previous\s+instructions/i',
            '/system\s+prompt/i',
            '/jailbreak/i',
            '/developer\s+mode/i',
            '/do\s+not\s+follow/i',
            '/override\s+instructions/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function getResearchesFullTextColumns(): string
    {
        $columns = $this->getResearchesFullTextIndexColumns();
        return implode(', ', array_map(static fn ($column) => 'researches.' . $column, $columns));
    }

    private function getDetailsFullTextColumns(): string
    {
        $columns = $this->getDetailsFullTextIndexColumns();

        return implode(', ', array_map(static fn ($column) => 'research_details.' . $column, $columns));
    }

    private function buildSmartSearchScore(string $query, bool $includeIndexedText = true): string
    {
        $normalized = mb_strtolower(trim($query));
        $escapedExact = $this->db->escape($normalized);
        $escapedLike = $this->db->escape('%' . $this->db->escapeLikeString($normalized) . '%');

        $parts = [
            "CASE WHEN LOWER(researches.title) = {$escapedExact} THEN 140 ELSE 0 END",
            "CASE WHEN LOWER(researches.title) LIKE {$escapedLike} THEN 80 ELSE 0 END",
            "CASE WHEN LOWER(researches.author) LIKE {$escapedLike} THEN 50 ELSE 0 END",
            "CASE WHEN LOWER(research_details.subjects) LIKE {$escapedLike} THEN 40 ELSE 0 END",
            "CASE WHEN LOWER(research_details.knowledge_type) LIKE {$escapedLike} THEN 25 ELSE 0 END",
            "CASE WHEN LOWER(research_details.publisher) LIKE {$escapedLike} THEN 20 ELSE 0 END",
            "CASE WHEN LOWER(research_details.physical_description) LIKE {$escapedLike} THEN 18 ELSE 0 END",
            "CASE WHEN LOWER(research_details.isbn_issn) LIKE {$escapedLike} THEN 15 ELSE 0 END",
        ];

        if ($this->hasResearchesFullTextIndex()) {
            $researchesColumns = $this->getResearchesFullTextColumns();
            $parts[] = "COALESCE(MATCH({$researchesColumns}) AGAINST ({$escapedExact} IN NATURAL LANGUAGE MODE), 0) * 45";
        }
        if ($includeIndexedText && $this->hasDetailsFullTextIndex()) {
            $detailsColumns = $this->getDetailsFullTextColumns();
            $parts[] = "COALESCE(MATCH({$detailsColumns}) AGAINST ({$escapedExact} IN NATURAL LANGUAGE MODE), 0) * 30";
        }

        if ($includeIndexedText && $this->hasSearchTextColumn()) {
            $parts[] = "CASE WHEN LOWER(research_details.search_text) LIKE {$escapedLike} THEN 22 ELSE 0 END";
        }

        foreach ($this->tokenizeSearchQuery($query) as $token) {
            $tokenLike = $this->db->escape('%' . $this->db->escapeLikeString($token) . '%');
            $parts[] = "CASE WHEN LOWER(researches.title) LIKE {$tokenLike} THEN 16 ELSE 0 END";
            $parts[] = "CASE WHEN LOWER(researches.author) LIKE {$tokenLike} THEN 12 ELSE 0 END";
            $parts[] = "CASE WHEN LOWER(research_details.subjects) LIKE {$tokenLike} THEN 9 ELSE 0 END";
            $parts[] = "CASE WHEN LOWER(research_details.physical_description) LIKE {$tokenLike} THEN 7 ELSE 0 END";
            if ($includeIndexedText && $this->hasSearchTextColumn()) {
                $parts[] = "CASE WHEN LOWER(research_details.search_text) LIKE {$tokenLike} THEN 6 ELSE 0 END";
            }
        }

        return '(' . implode(' + ', $parts) . ')';
    }

    private function applySmartSearchFilter($builder, string $query, bool $includeIndexedText = true): void
    {
        $normalized = trim($query);
        $tokens = $this->tokenizeSearchQuery($query);

        $builder->groupStart()
            ->like('researches.title', $normalized)
            ->orLike('researches.author', $normalized)
            ->orLike('research_details.subjects', $normalized)
            ->orLike('research_details.knowledge_type', $normalized)
            ->orLike('research_details.publisher', $normalized)
            ->orLike('research_details.physical_description', $normalized)
            ->orLike('research_details.isbn_issn', $normalized);

        if ($this->hasResearchesFullTextIndex()) {
            $researchesColumns = $this->getResearchesFullTextColumns();
            $escaped = $this->db->escape($normalized);
            $builder->orWhere("MATCH({$researchesColumns}) AGAINST ({$escaped} IN NATURAL LANGUAGE MODE) > 0", null, false);
        }
        if ($includeIndexedText && $this->hasDetailsFullTextIndex()) {
            $detailsColumns = $this->getDetailsFullTextColumns();
            $escaped = $this->db->escape($normalized);
            $builder->orWhere("MATCH({$detailsColumns}) AGAINST ({$escaped} IN NATURAL LANGUAGE MODE) > 0", null, false);
        }

        if ($includeIndexedText && $this->hasSearchTextColumn()) {
            $builder->orLike('research_details.search_text', $normalized);
        }

        foreach ($tokens as $token) {
            $builder->orLike('researches.title', $token)
                ->orLike('researches.author', $token)
                ->orLike('research_details.subjects', $token)
                ->orLike('research_details.physical_description', $token);

            if ($includeIndexedText && $this->hasSearchTextColumn()) {
                $builder->orLike('research_details.search_text', $token);
            }
        }

        $builder->groupEnd();
    }

    private function extractQuotedPhrases(string $query): array
    {
        if (!preg_match_all('/"([^"]+)"/u', $query, $matches)) {
            return [];
        }

        $phrases = array_map(static fn ($value) => trim((string) $value), $matches[1] ?? []);
        $phrases = array_values(array_unique(array_filter($phrases, static fn ($value) => mb_strlen((string) $value) >= 3)));

        return array_slice($phrases, 0, 4);
    }

    private function applySpecificSearchFilter($builder, string $query, bool $includeIndexedText = true): void
    {
        $normalized = trim($query);
        $tokens = $this->tokenizeSearchQuery($normalized);
        $phrases = $this->extractQuotedPhrases($normalized);

        if (empty($tokens) && empty($phrases)) {
            $this->applySmartSearchFilter($builder, $normalized, $includeIndexedText);
            return;
        }

        if ($includeIndexedText) {
            $terms = [];
            foreach ($phrases as $phrase) {
                $cleanPhrase = $this->sanitizeBooleanPhrase((string) $phrase);
                if ($cleanPhrase !== '') {
                    $terms[] = '+"' . str_replace('"', '', $cleanPhrase) . '"';
                }
            }
            foreach ($tokens as $token) {
                $cleanToken = $this->sanitizeBooleanToken((string) $token);
                if ($cleanToken !== '') {
                    $terms[] = '+' . $cleanToken . '*';
                }
            }

            if (!empty($terms)) {
                $booleanQuery = implode(' ', $terms);
                $escapedBoolean = $this->db->escape($booleanQuery);
                $matchClauses = [];

                if ($this->hasResearchesFullTextIndex()) {
                    $researchesColumns = $this->getResearchesFullTextColumns();
                    $matchClauses[] = "MATCH({$researchesColumns}) AGAINST ({$escapedBoolean} IN BOOLEAN MODE) > 0";
                }

                if ($this->hasDetailsFullTextIndex()) {
                    $detailsColumns = $this->getDetailsFullTextColumns();
                    $matchClauses[] = "MATCH({$detailsColumns}) AGAINST ({$escapedBoolean} IN BOOLEAN MODE) > 0";
                }

                if (!empty($matchClauses)) {
                    $builder->where(
                        '(' . implode(' OR ', $matchClauses) . ')',
                        null,
                        false
                    );
                    return;
                }
            }
        }

        foreach ($phrases as $phrase) {
            $builder->groupStart()
                ->like('researches.title', $phrase)
                ->orLike('researches.author', $phrase)
                ->orLike('research_details.subjects', $phrase)
                ->orLike('research_details.publisher', $phrase)
                ->orLike('research_details.physical_description', $phrase)
                ->orLike('research_details.isbn_issn', $phrase);

            if ($includeIndexedText && $this->hasSearchTextColumn()) {
                $builder->orLike('research_details.search_text', $phrase);
            }

            $builder->groupEnd();
        }

        foreach ($tokens as $token) {
            $builder->groupStart()
                ->like('researches.title', $token)
                ->orLike('researches.author', $token)
                ->orLike('research_details.subjects', $token)
                ->orLike('research_details.knowledge_type', $token)
                ->orLike('research_details.publisher', $token)
                ->orLike('research_details.physical_description', $token)
                ->orLike('research_details.isbn_issn', $token);

            if ($includeIndexedText && $this->hasSearchTextColumn()) {
                $builder->orLike('research_details.search_text', $token);
            }

            $builder->groupEnd();
        }
    }

    private function applyExactPhraseSearchFilter($builder, string $query, bool $includeIndexedText = true): void
    {
        $normalized = trim($query);
        if ($normalized === '') {
            return;
        }

        $phrases = $this->extractQuotedPhrases($normalized);
        if (empty($phrases)) {
            $phrases = [trim($normalized, " \t\n\r\0\x0B\"'")];
        }

        $phrases = array_values(array_unique(array_filter($phrases, static fn ($value) => mb_strlen((string) $value) >= 2)));
        if (empty($phrases)) {
            $this->applySpecificSearchFilter($builder, $normalized, $includeIndexedText);
            return;
        }

        foreach ($phrases as $phrase) {
            $builder->groupStart()
                ->like('researches.title', $phrase)
                ->orLike('researches.author', $phrase)
                ->orLike('research_details.subjects', $phrase)
                ->orLike('research_details.knowledge_type', $phrase)
                ->orLike('research_details.publisher', $phrase)
                ->orLike('research_details.physical_description', $phrase)
                ->orLike('research_details.isbn_issn', $phrase);

            if ($includeIndexedText && $this->hasSearchTextColumn()) {
                $builder->orLike('research_details.search_text', $phrase);
            }

            if ($includeIndexedText && $this->hasResearchesFullTextIndex()) {
                $cleanPhrase = $this->sanitizeBooleanPhrase((string) $phrase);
                if ($cleanPhrase === '') {
                    $cleanPhrase = trim((string) $phrase);
                }
                $escapedPhrase = $this->db->escape('"' . str_replace('"', '', $cleanPhrase) . '"');
                $researchesColumns = $this->getResearchesFullTextColumns();
                $builder->orWhere("MATCH({$researchesColumns}) AGAINST ({$escapedPhrase} IN BOOLEAN MODE) > 0", null, false);
            }

            if ($includeIndexedText && $this->hasDetailsFullTextIndex()) {
                $cleanPhrase = $this->sanitizeBooleanPhrase((string) $phrase);
                if ($cleanPhrase === '') {
                    $cleanPhrase = trim((string) $phrase);
                }
                $escapedPhrase = $this->db->escape('"' . str_replace('"', '', $cleanPhrase) . '"');
                $detailsColumns = $this->getDetailsFullTextColumns();
                $builder->orWhere("MATCH({$detailsColumns}) AGAINST ({$escapedPhrase} IN BOOLEAN MODE) > 0", null, false);
            }

            $builder->groupEnd();
        }
    }

    private function normalizeIndexText(string $text): string
    {
        if ($this->hasPromptInjectionPattern($text)) {
            $text = preg_replace('/ignore\s+previous\s+instructions/iu', ' ', $text) ?? $text;
            $text = preg_replace('/system\s+prompt/iu', ' ', $text) ?? $text;
            $text = preg_replace('/developer\s+mode/iu', ' ', $text) ?? $text;
            $text = preg_replace('/override\s+instructions/iu', ' ', $text) ?? $text;
            $text = preg_replace('/jailbreak/iu', ' ', $text) ?? $text;
        }

        $text = preg_replace('/[[:cntrl:]]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) > self::MAX_INDEX_TEXT_CHARS) {
            $text = mb_substr($text, 0, self::MAX_INDEX_TEXT_CHARS);
        }

        return $text;
    }

    private function decodePdfEscapes(string $value): string
    {
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', static function ($matches) {
            $code = octdec($matches[1]);
            if ($code < 0 || $code > 255) {
                return ' ';
            }
            return chr($code);
        }, $value) ?? $value;

        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\b' => "\b",
            '\\f' => "\f",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ];

        return strtr($value, $replacements);
    }

    private function tryExtractWithPdftotext(string $absolutePath): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $escapedPath = escapeshellarg($absolutePath);
        $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
        $command = "pdftotext -q -enc UTF-8 -nopgbrk {$escapedPath} - 2>{$nullDevice}";

        $output = @shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        return $output;
    }

    private function tryExtractWithOcr(string $absolutePath): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        if (!$this->hasPdftoppmBinary() || !$this->hasTesseractBinary()) {
            return null;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nullDevice = $isWindows ? 'NUL' : '/dev/null';

        try {
            $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'ocr_' . bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'ocr_' . uniqid('', true);
        }

        $maxPages = $this->getOcrMaxPages();
        $dpi = $this->getOcrDpi();
        $language = $this->getOcrLanguage();

        $escapedPdf = escapeshellarg($absolutePath);
        $escapedBase = escapeshellarg($tmpBase);
        $pdftoppmCmd = "pdftoppm -f 1 -l {$maxPages} -r {$dpi} -png {$escapedPdf} {$escapedBase} 2>{$nullDevice}";

        @shell_exec($pdftoppmCmd);

        $images = glob($tmpBase . '-*.png');
        if (!is_array($images) || empty($images)) {
            return null;
        }

        natsort($images);
        $images = array_values($images);

        $texts = [];
        foreach ($images as $index => $image) {
            if ($index >= $maxPages) {
                break;
            }

            if (!is_file($image)) {
                continue;
            }

            $escapedImage = escapeshellarg($image);
            $escapedLang = escapeshellarg($language);
            $ocrCmd = "tesseract {$escapedImage} stdout -l {$escapedLang} --psm 6 2>{$nullDevice}";
            $ocrText = @shell_exec($ocrCmd);

            if (is_string($ocrText) && trim($ocrText) !== '') {
                $texts[] = $ocrText;
            }
        }

        foreach (glob($tmpBase . '-*.png') ?: [] as $image) {
            @unlink($image);
        }

        if (empty($texts)) {
            return null;
        }

        return implode("\n", $texts);
    }

    private function extractTextFromPdfFallback(string $absolutePath): string
    {
        $size = @filesize($absolutePath);
        if ($size === false || $size <= 0) {
            return '';
        }

        if ($size > (self::MAX_FALLBACK_PDF_BYTES * 3)) {
            log_message('warning', '[PDF Search Index] Fallback skipped for large file: ' . basename($absolutePath));
            return '';
        }

        $handle = @fopen($absolutePath, 'rb');
        if (!$handle) {
            return '';
        }

        $buffer = '';
        $remaining = self::MAX_FALLBACK_PDF_BYTES;

        while (!feof($handle) && $remaining > 0) {
            $chunk = fread($handle, min(65536, $remaining));
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($handle);

        if ($buffer === '') {
            return '';
        }

        $texts = [];

        if (preg_match_all('/stream[\r\n]+(.*?)endstream/s', $buffer, $streamMatches)) {
            foreach ($streamMatches[1] as $stream) {
                $decoded = $stream;
                $inflated = @gzuncompress($stream);
                if (is_string($inflated) && $inflated !== '') {
                    $decoded = $inflated;
                } else {
                    $inflated = @gzdecode($stream);
                    if (is_string($inflated) && $inflated !== '') {
                        $decoded = $inflated;
                    }
                }

                if (preg_match_all('/\((.*?)\)\s*T[Jj]/s', $decoded, $textMatches)) {
                    foreach ($textMatches[1] as $candidate) {
                        $decodedText = $this->decodePdfEscapes((string) $candidate);
                        if (trim($decodedText) !== '') {
                            $texts[] = $decodedText;
                        }
                    }
                }
            }
        }

        if (empty($texts) && preg_match_all('/[A-Za-z][A-Za-z0-9,\.\-\(\)\/ ]{5,}/', $buffer, $plainMatches)) {
            $texts = array_slice($plainMatches[0], 0, 500);
        }

        return implode(' ', $texts);
    }

    private function extractPdfText(string $absolutePath): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return '';
        }

        $text = $this->tryExtractWithPdftotext($absolutePath);
        if ($text === null || trim($text) === '') {
            $text = $this->extractTextFromPdfFallback($absolutePath);
        }
        $normalized = $this->normalizeIndexText((string) $text);

        if (mb_strlen($normalized) < self::OCR_MIN_TRIGGER_TEXT_CHARS) {
            $ocrText = $this->tryExtractWithOcr($absolutePath);
            if (is_string($ocrText) && trim($ocrText) !== '') {
                $normalized = $this->normalizeIndexText(trim($normalized . ' ' . $ocrText));
            }
        }

        return $normalized;
    }

    private function buildSearchIndexText(array $research, array $details, ?string $pdfPath = null): string
    {
        $pdfText = '';
        if ($pdfPath !== null && $pdfPath !== '') {
            $absolutePath = ROOTPATH . 'public/uploads/' . basename($pdfPath);
            $pdfText = $this->extractPdfText($absolutePath);
        }

        $parts = [
            'title ' . ($research['title'] ?? ''),
            'author ' . ($research['author'] ?? ''),
            'crop ' . ($research['crop_variation'] ?? ''),
            'type ' . ($details['knowledge_type'] ?? ''),
            'publisher ' . ($details['publisher'] ?? ''),
            'isbn ' . ($details['isbn_issn'] ?? ''),
            'subjects ' . ($details['subjects'] ?? ''),
            'description ' . ($details['physical_description'] ?? ''),
            'shelf ' . ($details['shelf_location'] ?? ''),
            'pdf ' . $pdfText,
        ];

        return $this->normalizeIndexText(implode(' ', $parts));
    }

    private function hasIndexJobsTable(): bool
    {
        return $this->db->tableExists('research_index_jobs');
    }

    private function getIndexMaxAttempts(): int
    {
        $value = (int) env('search.index.maxAttempts', 3);
        if ($value < 1) {
            return 1;
        }
        if ($value > 10) {
            return 10;
        }
        return $value;
    }

    public function enqueueIndexJob(int $researchId, string $reason = 'update', int $priority = 100): ?int
    {
        if ($researchId <= 0 || !$this->hasIndexJobsTable()) {
            return null;
        }

        $priority = max(1, min(1000, $priority));
        $now = date('Y-m-d H:i:s');

        $existing = $this->indexJobModel
            ->where('research_id', $researchId)
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('id', 'DESC')
            ->first();

        if ($existing) {
            $this->indexJobModel->update((int) $existing['id'], [
                'reason' => mb_substr($reason, 0, 100),
                'priority' => min((int) ($existing['priority'] ?? $priority), $priority),
                'next_retry_at' => null,
                'updated_at' => $now,
            ]);
            return (int) $existing['id'];
        }

        $id = $this->indexJobModel->insert([
            'research_id' => $researchId,
            'status' => 'pending',
            'reason' => mb_substr($reason, 0, 100),
            'attempt_count' => 0,
            'max_attempts' => $this->getIndexMaxAttempts(),
            'priority' => $priority,
            'next_retry_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], true);

        return $id ? (int) $id : null;
    }

    private function completeIndexJob(int $jobId): void
    {
        if ($jobId <= 0 || !$this->hasIndexJobsTable()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->indexJobModel->update($jobId, [
            'status' => 'completed',
            'last_error' => null,
            'next_retry_at' => null,
            'completed_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function queueAndRefreshSearchIndex(int $researchId, string $reason = 'update', int $priority = 100): bool
    {
        if ($researchId <= 0 || !$this->hasSearchTextColumn()) {
            return false;
        }

        $jobId = $this->enqueueIndexJob($researchId, $reason, $priority);

        try {
            $ok = $this->refreshSearchIndex($researchId);
            if ($ok && $jobId !== null) {
                $this->completeIndexJob($jobId);
            }

            return $ok;
        } catch (\Throwable $e) {
            log_message(
                'error',
                '[Search Index] Immediate refresh failed for research #' . $researchId . ': ' . $e->getMessage()
            );

            return false;
        }
    }

    public function processPendingIndexJobs(int $limit = 20): array
    {
        if (!$this->hasIndexJobsTable()) {
            return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'requeued' => 0];
        }

        $limit = max(1, min(200, $limit));
        $now = date('Y-m-d H:i:s');
        $processed = 0;
        $completed = 0;
        $failed = 0;
        $requeued = 0;

        $jobs = $this->db->table('research_index_jobs')
            ->groupStart()
                ->where('status', 'pending')
                ->orGroupStart()
                    ->where('status', 'failed')
                    ->where('attempt_count < max_attempts', null, false)
                ->groupEnd()
            ->groupEnd()
            ->groupStart()
                ->where('next_retry_at', null)
                ->orWhere('next_retry_at <=', $now)
            ->groupEnd()
            ->orderBy('priority', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        foreach ($jobs as $job) {
            $jobId = (int) ($job['id'] ?? 0);
            $researchId = (int) ($job['research_id'] ?? 0);
            if ($jobId <= 0 || $researchId <= 0) {
                continue;
            }

            $processed++;
            $attemptCount = ((int) ($job['attempt_count'] ?? 0)) + 1;
            $maxAttempts = max(1, (int) ($job['max_attempts'] ?? $this->getIndexMaxAttempts()));

            $this->indexJobModel->update($jobId, [
                'status' => 'processing',
                'attempt_count' => $attemptCount,
                'started_at' => $now,
                'updated_at' => $now,
            ]);

            try {
                $ok = $this->refreshSearchIndex($researchId);
                if ($ok) {
                    $completed++;
                    $this->completeIndexJob($jobId);
                    continue;
                }

                throw new \RuntimeException('Index build returned empty/failed');
            } catch (\Throwable $e) {
                if ($attemptCount >= $maxAttempts) {
                    $failed++;
                    $this->indexJobModel->update($jobId, [
                        'status' => 'failed',
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                        'next_retry_at' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $requeued++;
                    $retryAt = date('Y-m-d H:i:s', strtotime('+' . (2 ** min(6, $attemptCount)) . ' minutes'));
                    $this->indexJobModel->update($jobId, [
                        'status' => 'failed',
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                        'next_retry_at' => $retryAt,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        return [
            'processed' => $processed,
            'completed' => $completed,
            'failed' => $failed,
            'requeued' => $requeued,
        ];
    }

    public function refreshSearchIndex(int $researchId): bool
    {
        if (!$this->hasSearchTextColumn()) {
            return false;
        }

        $row = $this->db->table('researches')
            ->select('researches.id, researches.title, researches.author, researches.crop_variation, researches.file_path, research_details.knowledge_type, research_details.publisher, research_details.isbn_issn, research_details.subjects, research_details.physical_description, research_details.shelf_location')
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.id', $researchId)
            ->get()
            ->getRowArray();

        if (!$row) {
            return false;
        }

        $research = [
            'title' => $row['title'] ?? '',
            'author' => $row['author'] ?? '',
            'crop_variation' => $row['crop_variation'] ?? '',
        ];

        $details = [
            'knowledge_type' => $row['knowledge_type'] ?? '',
            'publisher' => $row['publisher'] ?? '',
            'isbn_issn' => $row['isbn_issn'] ?? '',
            'subjects' => $row['subjects'] ?? '',
            'physical_description' => $row['physical_description'] ?? '',
            'shelf_location' => $row['shelf_location'] ?? '',
        ];

        $searchText = $this->buildSearchIndexText($research, $details, (string) ($row['file_path'] ?? ''));

        $this->db->table('research_details')
            ->where('research_id', $researchId)
            ->set([
                'search_text' => $searchText,
            ])
            ->update();

        return true;
    }

    /**
     * Parse various date formats into YYYY-MM-DD
     * Handles: '2018', 'January-June 2006', '01/02/2014'
     */
    private function parseFlexibleDate($dateStr)
    {
        if (empty($dateStr)) return date('Y-m-d'); // Default to Today

        $dateStr = trim($dateStr);

        // 1. Year Only (e.g. "2018") -> "2018-01-01"
        if (preg_match('/^\d{4}$/', $dateStr)) {
            return $dateStr . '-01-01';
        }

        // 2. Month-Month Year (e.g. "January-June 2006", "January -June 2010")
        // Regex: (Month Name)(Space?)-(Space?)(Month Name) (4 Digit Year)
        if (preg_match('/^([a-zA-Z]+)\s*[-]\s*[a-zA-Z]+\s+(\d{4})$/', $dateStr, $matches)) {
            // matches[1] = January, matches[2] = 2006
            $time = strtotime($matches[1] . ' ' . $matches[2]);
            if ($time) return date('Y-m-d', $time);
        }

        // 3. Slashes/Dashes (01/02/2014)
        // Check if it's already YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }

        // Try standard parsing
        $time = strtotime($dateStr);
        if ($time) {
            return date('Y-m-d', $time);
        }

        // Fallback: If unreadable, return null instead of corrupting data with today's date
        log_message('warning', "parseFlexibleDate: Could not parse date string '{$dateStr}'. Setting to null.");
        return null; 
    }

    private function normalizeSearchMode(?string $searchMode, bool $strictSearchFallback = false): string
    {
        $normalized = strtolower(trim((string) $searchMode));
        if ($normalized === '') {
            return $strictSearchFallback ? 'specific' : 'broad';
        }

        if (!in_array($normalized, ['broad', 'specific', 'exact'], true)) {
            return $strictSearchFallback ? 'specific' : 'broad';
        }

        return $normalized;
    }

    private function shouldIncludeIndexedText(?string $searchScope): bool
    {
        $normalized = strtolower(trim((string) $searchScope));
        if ($normalized === '') {
            return true;
        }

        return $normalized !== 'metadata';
    }

    private function sanitizeFilterValue($value, int $maxLength = 120): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            $normalized = mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    private function applyAdvancedFilters($builder, array $filters, bool $includePrivate): void
    {
        $knowledgeType = $this->sanitizeFilterValue($filters['knowledge_type'] ?? null, 100);
        $author = $this->sanitizeFilterValue($filters['author'] ?? null, 120);
        $cropVariation = $this->sanitizeFilterValue($filters['crop_variation'] ?? null, 120);
        $accessLevel = strtolower(trim((string) ($filters['access_level'] ?? '')));

        if ($knowledgeType !== null) {
            $builder->like('research_details.knowledge_type', $knowledgeType);
        }

        if ($author !== null) {
            $builder->like('researches.author', $author);
        }

        if ($cropVariation !== null) {
            $builder->like('researches.crop_variation', $cropVariation);
        }

        if ($this->hasAccessLevelColumn() && $includePrivate && in_array($accessLevel, ['public', 'private'], true)) {
            $builder->where('researches.access_level', $accessLevel);
        }
    }

    // --- READ METHODS ---

    public function getAllApproved(
        $startDate = null,
        $endDate = null,
        bool $includePrivate = false,
        ?string $searchQuery = null,
        bool $strictSearch = false,
        ?int $limit = null,
        ?array $filters = null,
        ?string $searchMode = null,
        ?string $searchScope = null
    )
    {
        $builder = $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'approved');

        if ($this->hasAccessLevelColumn() && !$includePrivate) {
            $builder->where('researches.access_level', 'public');
        }

        if ($startDate) {
            $builder->where('research_details.publication_date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('research_details.publication_date <=', $endDate);
        }

        $this->applyAdvancedFilters($builder, $filters ?? [], $includePrivate);

        $effectiveMode = $this->normalizeSearchMode($searchMode, $strictSearch);
        $includeIndexedText = $this->shouldIncludeIndexedText($searchScope);
        $searchQuery = trim((string) $searchQuery);
        if ($searchQuery !== '') {
            $correctedQuery = $this->applySpellingCorrections($searchQuery);
            $effectiveQuery = $effectiveMode === 'broad'
                ? $this->expandSearchQuery($correctedQuery)
                : $correctedQuery;

            $scoreExpression = $this->buildSmartSearchScore($effectiveQuery, $includeIndexedText);
            $builder->select($scoreExpression . ' AS relevance_score', false);
            if ($effectiveMode === 'exact') {
                $this->applyExactPhraseSearchFilter($builder, $effectiveQuery, $includeIndexedText);
            } elseif ($effectiveMode === 'specific') {
                $this->applySpecificSearchFilter($builder, $effectiveQuery, $includeIndexedText);
            } else {
                $this->applySmartSearchFilter($builder, $effectiveQuery, $includeIndexedText);
            }
            $builder->orderBy('relevance_score', 'DESC');
        }

        if ($limit !== null && $limit > 0) {
            $builder->limit(min(50, $limit));
        }

        $results = $builder->orderBy('researches.created_at', 'DESC')->findAll();

        return $results;
    }

    public function getTopViewedApproved(int $limit = 5, bool $includePrivate = false)
    {
        $safeLimit = max(1, min(50, $limit));

        $builder = $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'approved');

        if ($this->hasAccessLevelColumn() && !$includePrivate) {
            $builder->where('researches.access_level', 'public');
        }

        if ($this->hasViewCountColumn()) {
            $builder->orderBy('COALESCE(researches.view_count, 0)', 'DESC', false);
        }

        return $builder
            ->orderBy('researches.created_at', 'DESC')
            ->limit($safeLimit)
            ->findAll();
    }

    public function incrementViewCount(int $researchId, bool $includePrivate = false): bool
    {
        if (!$this->hasViewCountColumn()) {
            return false;
        }

        $builder = $this->db->table('researches')
            ->where('id', $researchId)
            ->where('status', 'approved');

        if ($this->hasAccessLevelColumn() && !$includePrivate) {
            $builder->where('access_level', 'public');
        }

        $builder
            ->set('view_count', 'COALESCE(view_count, 0) + 1', false)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->update();

        return (int) $this->db->affectedRows() > 0;
    }

    public function getAll()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->orderBy('researches.created_at', 'DESC')
            ->findAll();
    }

    public function getMySubmissions(int $userId)
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.uploaded_by', $userId)
            ->where('researches.status !=', 'archived')
            ->orderBy('researches.created_at', 'DESC')
            ->findAll();
    }

    public function getMyArchived(int $userId)
    {
        // Auto-delete old archived
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));
        $expiredIds = $this->researchModel->select('id')
            ->where('uploaded_by', $userId)
            ->where('status', 'archived')
            ->where('archived_at <', $cutoffDate)
            ->findColumn('id') ?? [];

        foreach ($expiredIds as $expiredId) {
            try {
                $this->purgeResearch((int) $expiredId);
            } catch (\Throwable $e) {
                log_message('warning', '[Archived Cleanup] Failed to purge research ID ' . (int) $expiredId . ': ' . $e->getMessage());
            }
        }

        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.uploaded_by', $userId)
            ->where('researches.status', 'archived')
            ->orderBy('researches.archived_at', 'DESC')
            ->findAll();
    }

    public function getAllArchived()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'archived')
            ->orderBy('researches.archived_at', 'DESC')
            ->findAll();
    }

    public function getPending()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'pending')
            ->orderBy('researches.created_at', 'DESC')
            ->findAll();
    }

    public function getRejected()
    {
        // Auto-delete old rejected
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        $expiredIds = $this->researchModel->select('id')
            ->where('status', 'rejected')
            ->where('rejected_at <', $cutoffDate)
            ->findColumn('id') ?? [];

        foreach ($expiredIds as $expiredId) {
            try {
                $this->purgeResearch((int) $expiredId);
            } catch (\Throwable $e) {
                log_message('warning', '[Rejected Cleanup] Failed to purge research ID ' . (int) $expiredId . ': ' . $e->getMessage());
            }
        }

        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'rejected')
            ->orderBy('researches.rejected_at', 'DESC')
            ->findAll();
    }

    public function getStats()
    {
        $approved = $this->researchModel->where('status', 'approved')->countAllResults();
        $pending = $this->researchModel->where('status', 'pending')->countAllResults();
        return ['total' => $approved, 'pending' => $pending];
    }

    public function getUserStats(int $userId)
    {
        $myPublished = $this->researchModel->where('uploaded_by', $userId)->where('status', 'approved')->countAllResults();
        $myPending = $this->researchModel->where('uploaded_by', $userId)->where('status', 'pending')->countAllResults();
        return ['published' => $myPublished, 'pending' => $myPending];
    }

    public function getComments($researchId)
    {
        return $this->commentModel->where('research_id', $researchId)->orderBy('created_at', 'ASC')->findAll();
    }

    public function getResearch(int $id)
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.id', $id)
            ->first();
    }

    public function getRecordsMissingPdf(int $limit = 1000): array
    {
        $safeLimit = max(1, min(1000, $limit));

        return $this->researchModel
            ->select('researches.id, researches.title, researches.author, researches.status, research_details.edition, research_details.isbn_issn')
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->groupStart()
                ->where('researches.file_path', null)
                ->orWhere('researches.file_path', '')
            ->groupEnd()
            ->orderBy('researches.title', 'ASC')
            ->limit($safeLimit)
            ->findAll();
    }

    public function purgeResearch(int $id): object
    {
        $item = $this->researchModel
            ->select('id, title, status, file_path, uploaded_by')
            ->where('id', $id)
            ->first();

        if (!$item) {
            throw new \RuntimeException('Research not found.');
        }

        [$originalFilePath, $stagedFilePath] = $this->stageResearchFileDeletion($item->file_path ?? null);

        $this->db->transStart();
        $this->notifModel->where('research_id', $id)->delete();
        $this->commentModel->where('research_id', $id)->delete();
        $this->indexJobModel->where('research_id', $id)->delete();
        $this->detailsModel->where('research_id', $id)->delete();
        $this->researchModel->delete($id);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            $this->restoreStagedResearchFile($originalFilePath, $stagedFilePath);
            throw new \RuntimeException('Failed to permanently delete the research record.');
        }

        $this->finalizeStagedResearchFileDeletion($stagedFilePath);

        return $item;
    }

    // --- WRITE METHODS ---

    public function checkDuplicate($title, $author, $isbn, $edition, $excludeId = null)
    {
        $builder = $this->db->table('researches');
        $builder->join('research_details', 'researches.id = research_details.research_id');

        // 1. Strict Title Check
        $builder->where('researches.title', $title);

        // 2. Strict Author Check (to allow same title by different authors)
        $builder->where('researches.author', $author);

        // 3. Strict Edition Check
        // If edition provided, match it. If empty, match ONLY empty/null editions.
        if (!empty($edition)) {
            $builder->where('research_details.edition', $edition);
        }
        else {
            $builder->groupStart()
                ->where('research_details.edition', '')
                ->orWhere('research_details.edition', null)
                ->groupEnd();
        }

        if ($excludeId) {
            $builder->where('researches.id !=', $excludeId);
        }

        if ($builder->countAllResults() > 0) {
            return "Duplicate! This Title/Author/Edition combination already exists.";
        }



        return false;
    }

    public function createResearch(int $userId, array $data, $file)
    {
        $this->db->transStart();

        $fileName = null;
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $fileName = $file->getRandomName();
            $targetDir = WRITEPATH . 'uploads/research';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $finalPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

            try {
                $encryptionService = new \App\Services\EncryptionService();
                $encryptionService->encryptFile($file->getTempName(), $finalPath);
            } catch (\Throwable $e) {
                throw new \Exception('Encryption failed: ' . $e->getMessage(), 500);
            }
        }

        $mainData = [
            'uploaded_by' => $userId,
            'title' => $data['title'],
            'author' => $data['author'],
            'crop_variation' => $data['crop_variation'],
            'status' => 'pending',
            'file_path' => $fileName,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->hasAccessLevelColumn()) {
            $mainData['access_level'] = self::DEFAULT_ACCESS_LEVEL;
        }

        $newResearchId = $this->researchModel->insert($mainData);

        // Create Logic
        $knowledgeType = $data['knowledge_type'];
        if (is_array($knowledgeType)) {
            $knowledgeType = implode(', ', $knowledgeType);
        }

        $detailsData = [
            'research_id' => $newResearchId,
            'knowledge_type' => $knowledgeType,
            'publication_date' => !empty($data['publication_date']) ? $data['publication_date'] : date('Y-m-d'),
            'edition' => $data['edition'],
            'publisher' => $data['publisher'],
            'physical_description' => $data['physical_description'],
            'isbn_issn' => $data['isbn_issn'],
            'subjects' => $data['subjects'],
            'shelf_location' => $data['shelf_location'],
            'item_condition' => $data['item_condition'],
            'link' => $data['link'],
        ];
        $this->detailsModel->insert($detailsData);

        // Notify Admins
        $admins = $this->userModel->where('role', 'admin')->findAll();

        foreach ($admins as $admin) {
            $this->notifModel->insert([
                'user_id' => $admin->id, // Entity access
                'sender_id' => $userId,
                'research_id' => $newResearchId,
                'message' => "New Submission: " . $data['title'],
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \Exception("Research creation failed.");
        }

        $this->queueAndRefreshSearchIndex((int) $newResearchId, 'create', 70);

        return $newResearchId;
    }

    public function updateResearch(int $id, int $userId, string $userRole, array $data, $file)
    {
        $item = $this->researchModel->find($id);

        if (!$item || ($item->uploaded_by != $userId && $userRole !== 'admin')) {
            throw new \Exception("Generic Forbidden", 403);
        }

        $this->db->transStart();

        $mainUpdate = [
            'title' => $data['title'],
            'author' => $data['author'],
            'crop_variation' => $data['crop_variation'],
        ];

        // If a researcher is updating a rejected item, put it back to pending
        if ($item->status === 'rejected' && $userRole !== 'admin') {
            $mainUpdate['status'] = 'pending';
            $mainUpdate['rejected_at'] = null; // Clear rejection date
        }

        if ($this->hasAccessLevelColumn() && $userRole === 'admin' && array_key_exists('access_level', $data)) {
            $mainUpdate['access_level'] = $this->normalizeAccessLevel((string) $data['access_level']);
        }

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $targetDir = WRITEPATH . 'uploads/research';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $finalPath = $targetDir . DIRECTORY_SEPARATOR . $newName;

            try {
                $encryptionService = new \App\Services\EncryptionService();
                $encryptionService->encryptFile($file->getTempName(), $finalPath);
            } catch (\Throwable $e) {
                throw new \Exception('Encryption failed: ' . $e->getMessage(), 500);
            }
            $mainUpdate['file_path'] = $newName;
        }
        $this->researchModel->update($id, $mainUpdate);

        $exists = $this->detailsModel->where('research_id', $id)->first();

        $knowledgeType = $data['knowledge_type'];
        if (is_array($knowledgeType)) {
            $knowledgeType = implode(', ', $knowledgeType);
        }

        $detailsData = [
            'knowledge_type' => $knowledgeType,
            'publication_date' => !empty($data['publication_date']) ? $data['publication_date'] : date('Y-m-d'),
            'edition' => $data['edition'],
            'publisher' => $data['publisher'],
            'physical_description' => $data['physical_description'],
            'isbn_issn' => $data['isbn_issn'],
            'subjects' => $data['subjects'],
            'shelf_location' => $data['shelf_location'],
            'item_condition' => $data['item_condition'],
            'link' => $data['link'],
        ];

        if ($exists) {
            $this->detailsModel->where('research_id', $id)->set($detailsData)->update();
        }
        else {
            $detailsData['research_id'] = $id;
            $this->detailsModel->insert($detailsData);
        }

        // Provide feedback loop back to admin during resubmission
        if ($item->status === 'rejected' && $userRole !== 'admin') {
            if (!empty($data['resubmit_remarks']) && trim($data['resubmit_remarks']) !== '') {
                // Get the user's name
                $user = $this->userModel->find($userId);
                $userName = $user ? $user->name : 'Researcher';
                
                $this->commentModel->insert([
                    'research_id' => $id,
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'role' => $userRole,
                    'comment' => "Resubmission Note: " . trim($data['resubmit_remarks']),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Notify Admins about the resubmission
            $admins = $this->userModel->where('role', 'admin')->findAll();
            foreach ($admins as $admin) {
                $this->notifModel->insert([
                    'user_id' => $admin->id,
                    'sender_id' => $userId,
                    'research_id' => $id,
                    'message' => "Item Resubmitted: " . $mainUpdate['title'],
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        $this->db->transComplete();
        if ($this->db->transStatus() === false) {
            throw new \Exception("Research update failed.");
        }

        $this->queueAndRefreshSearchIndex($id, 'update', 90);

        return true;
    }

    public function bulkUpdateAccessLevel(array $ids, string $accessLevel): array
    {
        if (!$this->hasAccessLevelColumn()) {
            throw new \RuntimeException('Visibility feature is not initialized. Run "php spark migrate" in backend.');
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if (empty($ids)) {
            return ['matched' => 0, 'updated' => 0];
        }

        $normalizedLevel = $this->normalizeAccessLevel($accessLevel);
        $matched = (int) $this->db->table('researches')->whereIn('id', $ids)->countAllResults();

        if ($matched === 0) {
            return ['matched' => 0, 'updated' => 0];
        }

        $this->db->table('researches')
            ->whereIn('id', $ids)
            ->set([
                'access_level' => $normalizedLevel,
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->update();

        $updated = max(0, (int) $this->db->affectedRows());

        return ['matched' => $matched, 'updated' => $updated];
    }

    public function setStatus(int $id, string $status, object $adminUser, string $messageTemplate, string $comment = '')
    {
        // For Approve/Reject/Archive
        $data = ['status' => $status];
        if ($status === 'approved')
            $data['approved_at'] = date('Y-m-d H:i:s');
        if ($status === 'rejected')
            $data['rejected_at'] = date('Y-m-d H:i:s');
        if ($status === 'archived')
            $data['archived_at'] = date('Y-m-d H:i:s');

        // For Restore
        if ($status === 'pending') {
            $data['rejected_at'] = null;
            $data['archived_at'] = null;
        }

        $this->db->transStart();
        $this->researchModel->update($id, $data);

        $item = $this->researchModel->find($id);
        if ($item && $item->uploaded_by) {
            $msg = sprintf($messageTemplate, $item->title);
            if (!empty($comment)) {
                $msg .= "\n\nRemarks: " . trim($comment);
            }
            
            $this->notifModel->insert([
                'user_id' => $item->uploaded_by,
                'sender_id' => $adminUser->id,
                'research_id' => $id,
                'message' => $msg,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!empty($comment)) {
                $this->db->table('research_comments')->insert([
                    'research_id' => $id,
                    'user_id' => $adminUser->id,
                    'user_name' => $adminUser->name ?? 'Admin',
                    'role' => $adminUser->role ?? 'admin',
                    'comment' => "[$status] " . trim($comment),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        $this->db->transComplete();
    }

    private function parseStatusTimestamp($value): ?int
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_object($value) && property_exists($value, 'date')) {
            $value = $value->date;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    private function inferArchivedRestoreStatus(object $item): string
    {
        $approvedAt = $this->parseStatusTimestamp($item->approved_at ?? null);
        $rejectedAt = $this->parseStatusTimestamp($item->rejected_at ?? null);

        if ($approvedAt !== null && ($rejectedAt === null || $approvedAt >= $rejectedAt)) {
            return 'approved';
        }

        if ($rejectedAt !== null) {
            return 'rejected';
        }

        return 'pending';
    }

    public function restoreArchived(int $id, object $adminUser, string $messageTemplate): string
    {
        $item = $this->researchModel->find($id);

        if (!$item) {
            throw new \RuntimeException('Research not found.');
        }

        $restoredStatus = $this->inferArchivedRestoreStatus($item);
        $data = [
            'status' => $restoredStatus,
            'archived_at' => null,
        ];

        // Pending items should come back as editable drafts, not as old rejected entries.
        if ($restoredStatus === 'pending') {
            $data['rejected_at'] = null;
        }

        $this->db->transStart();
        $this->researchModel->update($id, $data);

        $updatedItem = $this->researchModel->find($id);
        if ($updatedItem && $updatedItem->uploaded_by) {
            $msg = sprintf($messageTemplate, $updatedItem->title);
            $msg .= "\n\nCurrent status: " . ucfirst($restoredStatus) . '.';

            $this->notifModel->insert([
                'user_id' => $updatedItem->uploaded_by,
                'sender_id' => $adminUser->id,
                'research_id' => $id,
                'message' => $msg,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        $this->db->transComplete();

        return $restoredStatus;
    }

    public function extendDeadline($id, $newDate, $adminId)
    {
        $this->db->transStart();
        $this->researchModel->update($id, ['deadline_date' => $newDate]);

        $item = $this->researchModel->find($id);
        if ($item && $item->uploaded_by) {
            $formattedDate = date('M d, Y', strtotime($newDate));
            $this->notifModel->insert([
                'user_id' => $item->uploaded_by,
                'sender_id' => $adminId,
                'research_id' => $id,
                'message' => "📅 Deadline Updated: '{$item->title}' is due on {$formattedDate}.",
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        $this->db->transComplete();
    }

    public function addComment($data)
    {
        if ($this->commentModel->insert($data)) {
            $researchId = $data['research_id'];
            $senderId = $data['user_id'];
            $role = strtolower($data['role']);
            $commentText = $data['comment'];

            if ($role === 'admin') {
                $research = $this->researchModel->find($researchId);
                if ($research && isset($research->uploaded_by) && $research->uploaded_by != $senderId) {
                    $this->notifModel->insert([
                        'user_id' => $research->uploaded_by,
                        'sender_id' => $senderId,
                        'research_id' => $researchId,
                        'message' => "Admin commented: " . substr($commentText, 0, 15) . "...",
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            else {
                $admins = $this->userModel->where('role', 'admin')->findAll();
                foreach ($admins as $admin) {
                    if ($admin->id != $senderId) {
                        $this->notifModel->insert([
                            'user_id' => $admin->id,
                            'sender_id' => $senderId,
                            'research_id' => $researchId,
                            'message' => "New comment by {$data['user_name']}",
                            'is_read' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
            return true;
        }
        return false;
    }

    public function importSingleRow($rawData, $userId)
    {
        // Data Mapping
        $data = [
            'title' => $rawData['Title'] ?? 'Untitled',
            'knowledge_type' => $rawData['Type'] ?? 'Research Paper',
            'author' => $rawData['Author'] ?? $rawData['Authors'] ?? 'Unknown',
            'publication_date' => $this->parseFlexibleDate($rawData['Date'] ?? ''),
            'edition' => $rawData['Edition'] ?? $rawData['Publication'] ?? '',
            'publisher' => $rawData['Publisher'] ?? '',
            'physical_description' => $rawData['Pages'] ?? '',
            'isbn_issn' => $rawData['ISBN/ISSN'] ?? $rawData['ISSN'] ?? $rawData['ISBN'] ?? '',
            'subjects' => $rawData['Subjects'] ?? $rawData['Description'] ?? '',
            'shelf_location' => $rawData['Location'] ?? '',
            'item_condition' => $rawData['Condition'] ?? 'Good',
            'crop_variation' => $rawData['Crop'] ?? ''
        ];

        // 🚨 ADDED VALIDATION: Run data against rules before inserting
        $validation = \Config\Services::validation();
        $validationRules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'author' => 'required|min_length[2]|max_length[255]',
            'knowledge_type' => 'required|max_length[100]',
            'publication_date' => 'permit_empty|valid_date',
            'edition' => 'permit_empty|max_length[50]',
            'publisher' => 'permit_empty|max_length[255]',
            'physical_description' => 'permit_empty|max_length[255]',
            'isbn_issn' => 'permit_empty|max_length[50]|alpha_numeric_punct',
            'subjects' => 'permit_empty|string',
            'shelf_location' => 'permit_empty|max_length[100]',
            'item_condition' => 'permit_empty|max_length[50]',
            'crop_variation' => 'permit_empty|max_length[100]',
        ];
        
        $validation->setRules($validationRules);
        if (!$validation->run($data)) {
            $errors = implode(', ', $validation->getErrors());
            return ['status' => 'skipped', 'message' => 'Validation failed: ' . $errors];
        }

        $isbn = trim($data['isbn_issn']);
        $title = trim($data['title']);
        $edition = trim($data['edition']);

        // Check Duplicate
        $dupError = $this->checkDuplicate($title, $data['author'], $isbn, $edition);

        if ($dupError) {
            return ['status' => 'skipped', 'message' => 'Duplicate entry'];
        }

        $this->db->transStart();

        $mainData = [
            'title' => $title,
            'author' => $data['author'],
            'crop_variation' => $data['crop_variation'],
            'status' => 'approved',
            'uploaded_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if ($this->hasAccessLevelColumn()) {
            $mainData['access_level'] = self::DEFAULT_ACCESS_LEVEL;
        }

        $newId = $this->researchModel->insert($mainData);

        if ($newId) {
            $detailsData = [
                'research_id' => $newId,
                'knowledge_type' => $data['knowledge_type'],
                'publication_date' => $data['publication_date'],
                'edition' => $data['edition'],
                'publisher' => $data['publisher'],
                'physical_description' => $data['physical_description'],
                'isbn_issn' => $data['isbn_issn'],
                'subjects' => $data['subjects'],
                'shelf_location' => $data['shelf_location'],
                'item_condition' => $data['item_condition'],
                'link' => ''
            ];
            $this->detailsModel->insert($detailsData);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['status' => 'error', 'message' => 'Database transaction failed'];
        }

        $this->queueAndRefreshSearchIndex((int) $newId, 'import', 120);

        return ['status' => 'success', 'id' => $newId];
    }

    /**
     * Checks multiple CSV rows for duplicates and returns their real-time statuses.
     * This is an optimization for the frontend CSV Preview Table.
     */
    public function previewCsvDuplicates(array $rows)
    {
        // Step 1: Collect all titles in one pass
        $titles = [];
        foreach ($rows as $row) {
            $t = trim($row['Title'] ?? '');
            if ($t !== '') {
                $titles[] = $t;
            }
        }

        if (empty($titles)) {
            return array_fill(0, count($rows), ['status' => 'new']);
        }

        // Step 2: ONE query using whereIn()
        $existingRecords = $this->db->table('researches')
            ->select('researches.id, researches.title, researches.author, researches.file_path')
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->whereIn('researches.title', $titles)
            ->get()->getResult();

        // Step 3: Index by title for O(1) lookup
        $existingMap = [];
        foreach ($existingRecords as $record) {
            $existingMap[strtolower($record->title)][] = $record;
        }

        // Step 4: Classify each row
        $results = [];
        foreach ($rows as $idx => $rawData) {
            $title  = trim($rawData['Title'] ?? '');
            $author = trim($rawData['Author'] ?? $rawData['Authors'] ?? '');

            if ($title === '') {
                $results[$idx] = ['status' => 'new'];
                continue;
            }

            $matches = $existingMap[strtolower($title)] ?? [];
            if (empty($matches)) {
                $results[$idx] = ['status' => 'new'];
                continue;
            }

            // Filter by author if provided
            if ($author !== '') {
                $authorMatch = array_filter($matches, fn($r) => strtolower($r->author) === strtolower($author));
                $match = !empty($authorMatch) ? array_values($authorMatch)[0] : $matches[0];
            } else {
                $match = $matches[0];
            }

            if (!empty($match->file_path)) {
                $results[$idx] = ['status' => 'duplicate_with_pdf', 'title' => $match->title];
            } else {
                $results[$idx] = ['status' => 'duplicate_no_pdf', 'title' => $match->title];
            }
        }

        return $results;
    }

    public function importCsv($fileTempName, int $userId)
    {
        ini_set('auto_detect_line_endings', TRUE);

        $handle = fopen($fileTempName, 'r');
        if ($handle === false) {
             throw new \Exception('Failed to open CSV file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
             fclose($handle);
             throw new \Exception('CSV file is empty or missing headers.');
        }
        $headers = array_map('trim', $headers);

        // --- GUARD 1: UTF-8 / binary garbage (e.g. Excel .xlsx uploaded as CSV) ---
        foreach ($headers as $h) {
            if (!mb_check_encoding((string) $h, 'UTF-8')) {
                fclose($handle);
                throw new \Exception(
                    'Invalid file encoding. The file does not appear to be a valid UTF-8 CSV. ' .
                    'Please open it in a spreadsheet app, save as "CSV UTF-8", then upload again.'
                );
            }
        }

        // --- GUARD 2: Duplicate header names ---
        $headerCounts = array_count_values($headers);
        $duplicates   = array_keys(array_filter($headerCounts, static fn($c) => $c > 1));
        if (!empty($duplicates)) {
            fclose($handle);
            throw new \Exception(
                'Invalid CSV format. Duplicate column header(s) detected: ' .
                implode(', ', $duplicates) .
                '. Each column name must be unique. Please use the official template.'
            );
        }

        // --- GUARD 3: Strict column allowlist (must match template exactly) ---
        // Lists all names + accepted aliases understood by importSingleRow().
        $knownColumns = [
            'Title', 'Author', 'Authors', 'Type', 'Date',
            'Edition', 'Publication', 'Publisher', 'Pages',
            'ISBN/ISSN', 'ISSN', 'ISBN',
            'Subjects', 'Description',
            'Location', 'Condition', 'Crop',
        ];
        $unknownColumns = array_values(array_diff($headers, $knownColumns));
        if (!empty($unknownColumns)) {
            fclose($handle);
            throw new \Exception(
                'Invalid CSV format. Unrecognized column(s): ' . implode(', ', $unknownColumns) .
                '. Please download and use the official template. ' .
                'Accepted columns: ' . implode(', ', $knownColumns) . '.'
            );
        }

        // --- GUARD 4: Required columns logic has been shifted to the frontend and per-row validation ---
        // (If required fields are missing, the row will just fail CodeIgniter validation and be gracefully skipped).

        $count = 0;
        $skipped = 0;

        // Streaming rows to prevent memory exhaustion
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers))
                continue;

            $rawData = array_combine($headers, $row);
            $result = $this->importSingleRow($rawData, $userId);

            if ($result['status'] === 'success') {
                $count++;
            }
            else {
                $skipped++;
                log_message('warning', "CSV Import Skipped Row: " . ($result['message'] ?? 'Unknown error'));
            }
        }

        fclose($handle);

        return ['count' => $count, 'skipped' => $skipped];
    }
    /**
     * Match a PDF file to an existing research record and attach it.
     *
     * Matching priority:
     *   1. If $isbnHint or $editionHint are provided, use them to disambiguate among
     *      title matches to find the most specific record.
     *   2. Filename bracket notation is also parsed automatically:
     *        "Golden Roots [ISSN 1656-5444].pdf"  → isbn hint
     *        "Golden Roots [Vol. 1].pdf"           → edition hint
     *   3. Falls back to the first title-matched record that has no file yet.
     *   4. Returns 'exists' if all matching records already have files.
     *
     * @param string $titleCandidate  Filename (without extension) used as title search term.
     * @param mixed  $file            CI4 uploaded file object.
     * @param string $isbnHint        Optional explicit ISBN/ISSN to narrow the match.
     * @param string $editionHint     Optional explicit edition to narrow the match.
     * @return string 'linked' | 'exists' | 'no_match' | 'error_move'
     */
    /**
     * Finds the best matching record for a PDF based on title, ISBN, and Edition hints.
     */
    private function findPdfMatch(string $titleCandidate, string $isbnHint = '', string $editionHint = '')
    {
        // --- Step 1: Parse bracket hints from filename if not explicitly supplied ---
        $parsedTitle   = $titleCandidate;
        $parsedIsbn    = $isbnHint;
        $parsedEdition = $editionHint;

        if ($parsedIsbn === '' && $parsedEdition === '') {
            if (preg_match('/^(.+?)\s*\[([^\]]+)\]\s*$/', $titleCandidate, $m)) {
                $parsedTitle  = trim($m[1]);
                $bracketPart  = trim($m[2]);

                if (preg_match('/^(isbn|issn)\s*[:\-]?\s*(.+)$/i', $bracketPart, $idm)) {
                    $parsedIsbn = trim($idm[2]);
                } else {
                    $parsedEdition = $bracketPart;
                }
            }
        }

        // --- Step 2: Find ALL records whose title matches (case-insensitive) ---
        $matches = $this->researchModel
            ->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->like('researches.title', $parsedTitle, 'none')
            ->findAll();

        if (empty($matches)) {
            return ['status' => 'no_match', 'record' => null];
        }

        // --- Step 3: Disambiguate using ISBN/ISSN hint ---
        $best = null;

        if ($parsedIsbn !== '') {
            foreach ($matches as $candidate) {
                $dbIsbn = trim((string) ($candidate->isbn_issn ?? ''));
                if ($dbIsbn !== '' && stripos($dbIsbn, $parsedIsbn) !== false) {
                    if (empty($candidate->file_path)) {
                        $best = $candidate;
                        break;
                    }
                    if ($best === null) {
                        $best = $candidate;
                    }
                }
            }
        }

        // --- Step 4: Disambiguate using edition hint ---
        if ($best === null && $parsedEdition !== '') {
            foreach ($matches as $candidate) {
                $dbEdition = trim((string) ($candidate->edition ?? ''));
                if ($dbEdition !== '' && stripos($dbEdition, $parsedEdition) !== false) {
                    if (empty($candidate->file_path)) {
                        $best = $candidate;
                        break;
                    }
                    if ($best === null) {
                        $best = $candidate;
                    }
                }
            }
        }

        // --- Step 5: Fall back to first record without a file ---
        if ($best === null) {
            foreach ($matches as $candidate) {
                if (empty($candidate->file_path)) {
                    $best = $candidate;
                    break;
                }
            }
        }

        // --- Step 6: All matched records already have files ---
        if ($best === null || !empty($best->file_path)) {
            return ['status' => 'exists', 'record' => $best];
        }

        return ['status' => 'linked', 'record' => $best];
    }

    /**
     * Preview matches for an array of PDF filenames/hints without uploading anything
     */
    public function previewPdfMatches(array $files)
    {
        $results = [];
        foreach ($files as $fileReq) {
            $filename = trim((string) ($fileReq['filename'] ?? ''));
            if ($filename === '') continue;

            $titleCandidate = pathinfo($filename, PATHINFO_FILENAME);
            $isbnHint = trim((string) ($fileReq['isbnHint'] ?? ''));
            $editionHint = trim((string) ($fileReq['editionHint'] ?? ''));

            $matchResult = $this->findPdfMatch($titleCandidate, $isbnHint, $editionHint);
            
            $recordInfo = null;
            if ($matchResult['record']) {
                $r = $matchResult['record'];
                $recordInfo = [
                    'id' => $r->id,
                    'title' => $r->title,
                    'author' => $r->author ?? 'Unknown'
                ];
            }

            $results[] = [
                'filename' => $filename,
                'status' => $matchResult['status'],
                'record' => $recordInfo
            ];
        }
        return $results;
    }

    private function attachPdfToRecord($record, $file): string
    {
        if (!$record) {
            return 'no_match';
        }

        if (!empty($record->file_path)) {
            return 'exists';
        }

        $newName    = $file->getRandomName();
        $targetPath = WRITEPATH . 'uploads/research';
        if (!is_dir($targetPath)) mkdir($targetPath, 0750, true);
        $finalPath  = $targetPath . DIRECTORY_SEPARATOR . $newName;

        $enc       = new \App\Services\EncryptionService();
        $encrypted = false;
        try {
            $enc->encryptFile($file->getTempName(), $finalPath);
            $encrypted = true;
        } catch (\Throwable $e) {
            log_message('error', "[Bulk PDF] Encryption failed for '{$file->getClientName()}': " . $e->getMessage());
        }

        if ($encrypted) {
            $this->researchModel->update($record->id, ['file_path' => $newName]);
            $this->queueAndRefreshSearchIndex((int) $record->id, 'pdf_attach', 60);
            log_message('info', "[Bulk PDF] Linked: {$file->getClientName()} to '{$record->title}' (ID: {$record->id})");
            return 'linked';
        }

        return 'error_move';
    }

    public function attachPdfToResearchId(int $researchId, $file): string
    {
        if ($researchId <= 0) {
            return 'no_match';
        }

        $record = $this->researchModel->find($researchId);

        return $this->attachPdfToRecord($record, $file);
    }

    public function matchAndAttachPdf($titleCandidate, $file, string $isbnHint = '', string $editionHint = '')
    {
        $matchResult = $this->findPdfMatch($titleCandidate, $isbnHint, $editionHint);

        if ($matchResult['status'] === 'no_match') {
            log_message('info', "[Bulk PDF] No match found for title: {$titleCandidate}");
            return 'no_match';
        }
        if ($matchResult['status'] === 'exists') {
            log_message('info', "[Bulk PDF] All {$titleCandidate} record(s) already have files.");
            return 'exists';
        }

        return $this->attachPdfToRecord($matchResult['record'], $file);
    }
}
