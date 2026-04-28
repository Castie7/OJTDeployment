<?php

namespace App\Controllers;

use App\Services\ResearchService;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class ResearchController extends BaseController
{
    use ResponseTrait;

    private const MAX_PDF_SIZE_BYTES = 134217728; // 128 MB
    private const MAX_PDF_SIZE_MB = 128;

    protected $researchService;
    protected $authService;

    public function __construct()
    {
        $this->researchService = new ResearchService();
        $this->authService = new AuthService();
        helper('activity'); // Load Helper
    }

    // --- PDF STREAMING ---
    public function viewPdf($id = null)
    {
        // Option B implementation: guests may view approved PDFs. getUser() returns null for unauthenticated
        // requests. requireResearchAccess() permits guest access to approved research; non-approved requires ownership or admin.
        $user = $this->getUser();

        $researchId = (int) $id;
        try {
            $research = $this->researchService->getResearch($researchId);

            // SECURITY FIX: Enforce ownership/admin check for non-approved items.
            // A logged-in user must not be able to stream another user's pending/rejected PDF.
            $accessError = $this->requireResearchAccess($research, $user);
            if ($accessError !== null) {
                return $accessError;
            }

            if (empty($research->file_path)) {
                return $this->failNotFound('File not found.');
            }

            // Determine safe file path in the shielded directory
            $fileName = $research->file_path;
            $filePath = WRITEPATH . 'uploads/research/' . basename($fileName);

            if (!is_file($filePath)) {
                return $this->failNotFound('Encrypted file not found on disk.');
            }

            $isXhr = $this->request->getGet('xhr') == '1';

            // ---------------------------------------------------------------
            // CRITICAL: Use native PHP header() calls instead of CI4's
            // Response object. Because we call exit; after streaming, CI4's
            // after-filters (including CORS) never run, and CI4's internal
            // header buffer may not flush. Native header() goes directly to
            // Apache/the SAPI, guaranteeing they reach the browser.
            // ---------------------------------------------------------------

            // 1. CORS headers — required for cross-origin XHR from localhost:5173
            $origin = $this->request->getHeaderLine('Origin');
            if ($origin) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
            }

            // 2. Content headers — differ based on XHR vs direct iframe
            if ($isXhr) {
                // XHR Blob fetch: disguise as binary stream so IDM ignores it
                header('Content-Type: application/octet-stream');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            } else {
                // Direct iframe access (legacy fallback)
                $safeTitle = addcslashes($research->title . '.pdf', '"\\');
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $safeTitle . '"');
                header('Cache-Control: private, max-age=3600');
            }

            // 3. Stream the decrypted file directly to the output buffer
            $encryptionService = new \App\Services\EncryptionService();
            $encryptionService->streamDecryptToOutput($filePath);
            exit;
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 400);
        } catch (\Throwable $e) {
            log_message('error', '[Research View PDF] ' . $e->getMessage());
            return $this->failServerError('Failed to open file.');
        }
    }
    protected function getUser()
    {
        $request = service('request');
        // The user is attached to the request by the AuthFilter
        // Or we can just get it here to be safe if the filter wasn't structured to attach it.
        // Let's rely on the token/session directly here to get the Entity
        $token = $request->getHeaderLine('Authorization');
        return $this->authService->validateUser($token);
    }

    protected function validateUser()
    {
        return $this->getUser();
    }

    /*
     * Enforces the rule: non-approved research items (pending, rejected, archived)
     * may only be accessed by the item's owner or an admin.
     * Approved items marked as 'private' require the user to be logged in.
     * @param mixed $research  The research entity/object returned by getResearch().
     */
    private function requireResearchAccess($research, $user): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if (!$research) {
            return $this->failNotFound('Research not found.');
        }

        // Guard against model returning array vs entity object to prevent fatals
        $status      = is_object($research) ? $research->status       : ($research['status'] ?? null);
        $uploadedBy  = is_object($research) ? $research->uploaded_by  : ($research['uploaded_by'] ?? null);
        $accessLevel = is_object($research) ? ($research->access_level ?? 'private') : ($research['access_level'] ?? 'private');

        if ($status === 'approved') {
            // Approved and Public: Anyone can access
            if ($accessLevel !== 'private') {
                return null;
            }
            
            // Approved but Private: Requires ANY logged-in user
            if (!$user) {
                return $this->failUnauthorized('Unauthorized Access: This document is private. Please login to view.');
            }
            
            return null;
        }

        // Non-approved: only admins and the original uploader may proceed.
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        if ($user->role === 'admin' || (int) $uploadedBy === (int) $user->id) {
            return null;
        }

        return $this->failForbidden('Access Denied: You do not have permission to access this resource.');
    }

    private function isValidIsoDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function validatePdfFile($file, bool $required = false): ?string
    {
        if ($file === null) {
            return $required ? 'PDF file is required.' : null;
        }

        if (!$file->isValid()) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                return $required ? 'PDF file is required.' : null;
            }
            return 'File upload failed: ' . $file->getErrorString();
        }

        if ((int) $file->getSize() > self::MAX_PDF_SIZE_BYTES) {
            return 'PDF file exceeds maximum size of ' . self::MAX_PDF_SIZE_MB . ' MB.';
        }

        $clientExt = strtolower((string) $file->getClientExtension());
        if ($clientExt !== '' && $clientExt !== 'pdf') {
            return 'Only PDF files are allowed.';
        }

        $serverExt = strtolower((string) $file->guessExtension());
        if ($serverExt !== 'pdf') {
            return 'Only PDF files are allowed.';
        }

        $serverMime = strtolower((string) $file->getMimeType());
        if (!str_contains($serverMime, 'pdf')) {
            return 'Invalid file type. Only PDF files are allowed.';
        }

        return null;
    }

    private function normalizeAccessLevel(?string $accessLevel): string
    {
        $normalized = strtolower(trim((string) $accessLevel));

        return in_array($normalized, ['public', 'private'], true) ? $normalized : 'private';
    }

    private function parseBooleanQueryValue(?string $value): bool
    {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function parsePositiveIntQueryValue(?string $value, int $min = 1, int $max = 50): ?int
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (!ctype_digit($normalized)) {
            return null;
        }

        $parsed = (int) $normalized;
        if ($parsed < $min) {
            $parsed = $min;
        }
        if ($parsed > $max) {
            $parsed = $max;
        }

        return $parsed;
    }

    private function parseEnumQueryValue(?string $value, array $allowedValues): ?string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, $allowedValues, true) ? $normalized : null;
    }

    private function parseTextQueryValue(?string $value, int $maxLength = 255): ?string
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

    private function getOptionalJsonPayload(): array
    {
        $rawBody = trim((string) $this->request->getBody());
        if ($rawBody === '') {
            return [];
        }

        try {
            $json = $this->request->getJSON(true);
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            log_message('debug', '[ResearchController] Optional JSON parse skipped: ' . $e->getMessage());
            return [];
        }
    }

    // 1. PUBLIC INDEX
    public function index()
    {
        $user = $this->getUser();
        $includePrivate = (bool) $user;

        $startDate = $this->parseTextQueryValue($this->request->getGet('start_date'), 10);
        $endDate = $this->parseTextQueryValue($this->request->getGet('end_date'), 10);
        $search = $this->parseTextQueryValue($this->request->getGet('search'), 200);
        $strictSearch = $this->parseBooleanQueryValue($this->request->getGet('strict'));
        $knowledgeType = $this->parseTextQueryValue($this->request->getGet('knowledge_type'), 100);
        $authorFilter = $this->parseTextQueryValue($this->request->getGet('author'), 120);
        $cropVariation = $this->parseTextQueryValue($this->request->getGet('crop_variation'), 120);
        $accessLevelRaw = $this->request->getGet('access_level');
        $accessLevel = $this->parseEnumQueryValue($accessLevelRaw, ['public', 'private']);
        $searchModeRaw = $this->request->getGet('search_mode');
        $searchMode = $this->parseEnumQueryValue($searchModeRaw, ['broad', 'specific', 'exact']);
        $searchScopeRaw = $this->request->getGet('search_scope');
        $searchScope = $this->parseEnumQueryValue($searchScopeRaw, ['all', 'metadata']);
        $limitRaw = $this->request->getGet('limit');
        $limit = $this->parsePositiveIntQueryValue($limitRaw);

        if (trim((string) $limitRaw) !== '' && $limit === null) {
            return $this->fail('Invalid limit. Use a numeric value between 1 and 50.', 400);
        }

        if (trim((string) $accessLevelRaw) !== '' && $accessLevel === null) {
            return $this->fail('Invalid access_level. Use public or private.', 400);
        }

        if (trim((string) $searchModeRaw) !== '' && $searchMode === null) {
            return $this->fail('Invalid search_mode. Use broad, specific, or exact.', 400);
        }

        if (trim((string) $searchScopeRaw) !== '' && $searchScope === null) {
            return $this->fail('Invalid search_scope. Use all or metadata.', 400);
        }

        if ($searchMode === null) {
            $searchMode = $strictSearch ? 'specific' : 'broad';
        }
        if ($searchScope === null) {
            $includePdfRaw = $this->request->getGet('include_pdf');
            if (trim((string) $includePdfRaw) !== '') {
                $searchScope = $this->parseBooleanQueryValue($includePdfRaw) ? 'all' : 'metadata';
            } else {
                $searchScope = 'all';
            }
        }

        $filters = array_filter([
            'knowledge_type' => $knowledgeType,
            'author' => $authorFilter,
            'crop_variation' => $cropVariation,
            'access_level' => $accessLevel,
        ], static fn ($value) => $value !== null && $value !== '');

        if ($startDate !== null && !$this->isValidIsoDate($startDate)) {
            return $this->fail('Invalid start_date. Use YYYY-MM-DD format.', 400);
        }

        if ($endDate !== null && !$this->isValidIsoDate($endDate)) {
            return $this->fail('Invalid end_date. Use YYYY-MM-DD format.', 400);
        }

        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            return $this->fail('Invalid date range: start_date cannot be later than end_date.', 400);
        }

        $data = $this->researchService->getAllApproved(
            $startDate,
            $endDate,
            $includePrivate,
            $search,
            $strictSearch,
            $limit,
            $filters,
            $searchMode,
            $searchScope
        );
        return $this->respond($data);
    }

    public function topViewed()
    {
        $user = $this->getUser();
        $includePrivate = (bool) $user;

        $limitRaw = $this->request->getGet('limit');
        $limit = $this->parsePositiveIntQueryValue($limitRaw);
        if (trim((string) $limitRaw) !== '' && $limit === null) {
            return $this->fail('Invalid limit. Use a numeric value between 1 and 50.', 400);
        }

        $data = $this->researchService->getTopViewedApproved($limit ?? 5, $includePrivate);
        return $this->respond($data);
    }

    public function trackView($id = null)
    {
        if (!$id) {
            return $this->fail('Research ID required', 400);
        }

        $user = $this->getUser();

        // SECURITY FIX: Prevent view count inflation on non-approved items by
        // unauthorized users. Guests and non-owners cannot increment views for
        // pending, rejected, or archived research.
        $research = $this->researchService->getResearch((int) $id);
        $accessError = $this->requireResearchAccess($research, $user);
        if ($accessError !== null) {
            return $accessError;
        }

        $includePrivate = (bool) $user;
        $this->researchService->incrementViewCount((int) $id, $includePrivate);

        return $this->respond(['status' => 'success']);
    }

    // 2. MY SUBMISSIONS
    public function mySubmissions()
    {
        $user = $this->getUser();

        $data = $this->researchService->getMySubmissions($user->id);
        return $this->respond($data);
    }

    // 3. MY ARCHIVED
    public function myArchived()
    {
        $user = $this->getUser();

        $data = $this->researchService->getMyArchived($user->id);
        return $this->respond($data);
    }

    // 2.1 SINGLE ITEM (Admin or Owner)
    public function show($id = null)
    {
        $user = $this->validateUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        if (!$id) {
            return $this->fail('Research ID required', 400);
        }

        $item = $this->researchService->getResearch((int) $id);
        if (!$item) {
            return $this->failNotFound('Research not found');
        }

        if ($user->role !== 'admin' && (int) $item->uploaded_by !== (int) $user->id) {
            return $this->failForbidden('Access Denied');
        }

        return $this->respond($item);
    }

    public function archived()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden('Access Denied');

        $data = $this->researchService->getAllArchived();
        return $this->respond($data);
    }

    // 4. PENDING LIST
    public function pending()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden('Access Denied');

        $data = $this->researchService->getPending();
        return $this->respond($data);
    }

    // 5. REJECTED LIST
    public function rejectedList()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $data = $this->researchService->getRejected();
        return $this->respond($data);
    }

    // --- VALIDATION RULES HELPER ---
    private function getValidationRules()
    {
        return [
            'title' => 'required|min_length[3]|max_length[255]',
            'pdf_file' => 'permit_empty|max_size[pdf_file,256000]|ext_in[pdf_file,pdf,png,jpg,jpeg]',
            'author' => 'required|min_length[2]|max_length[255]',
            'knowledge_type' => 'required|max_length[100]',
            'publication_date' => 'permit_empty|valid_date',
            'start_date' => 'permit_empty|valid_date',
            'deadline_date' => 'permit_empty|valid_date',
            'edition' => 'permit_empty|max_length[50]',
            'publisher' => 'permit_empty|max_length[255]',
            'physical_description' => 'permit_empty|max_length[255]',
            'isbn_issn' => 'permit_empty|max_length[50]|alpha_numeric_punct',
            'subjects' => 'permit_empty|string',
            'shelf_location' => 'permit_empty|max_length[100]',
            'item_condition' => 'permit_empty|max_length[50]',
            'crop_variation' => 'permit_empty|max_length[100]',
            'link' => 'permit_empty|valid_url_strict',
            'access_level' => 'permit_empty|in_list[public,private]',
        ];
    }

    // 6. CREATE
    public function create()
    {
        try {
            $user = $this->getUser();

            log_message('debug', '[Research Create] Incoming POST: ' . json_encode($this->request->getPost()));
            log_message('debug', '[Research Create] Incoming FILES: ' . json_encode($_FILES));
            
            // Handle JSON vs Form Data
            // Wrap getJSON to prevent FormatException on file uploads
            $input = $this->request->getPost();
            if (empty($input) && str_contains((string)$this->request->getHeaderLine('Content-Type'), 'application/json')) {
                try {
                    $rawInput = $this->request->getJSON(true);
                    if (!empty($rawInput)) {
                        $input = $rawInput;
                    }
                } catch (\Throwable $e) {
                    log_message('debug', '[Research Create] JSON parse skipped or failed: ' . $e->getMessage());
                }
            }

            // New uploads always start private. Admins can publish them later from Masterlist.
            $input['access_level'] = 'private';

            // Validate
            $validation = \Config\Services::validation();
            $validation->setRules($this->getValidationRules());

            if (!$validation->run($input)) {
                return $this->response->setJSON(['status' => 'error', 'messages' => $validation->getErrors()])->setStatusCode(400);
            }

            // Duplicate Check
            $title = trim($input['title'] ?? '');
            $author = trim($input['author'] ?? '');
            $edition = trim($input['edition'] ?? '');
            $isbn = trim($input['isbn_issn'] ?? '');

            $dupError = $this->researchService->checkDuplicate($title, $author, $isbn, $edition);
            if ($dupError) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'messages' => ['duplicate' => $dupError]
                ])->setStatusCode(400);
            }

            $pdfFile = $this->request->getFile('pdf_file');
            $pdfValidationError = $this->validatePdfFile($pdfFile);
            if ($pdfValidationError !== null) {
                return $this->fail($pdfValidationError, 400);
            }

            $this->researchService->createResearch($user->id, $input, $pdfFile);

            // LOG
            log_activity($user->id, $user->name, $user->role, 'CREATE_RESEARCH', "Created research: " . ($input['title'] ?? 'Untitled'));

            return $this->respond(['status' => 'success']);
        }
        catch (\Throwable $e) {
            log_message('error', '[Research Create] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->failServerError('An unexpected server error occurred: ' . $e->getMessage());
        }
    }

    // 7. UPDATE
    public function update($id = null)
    {
        try {
            // Allow POST (standard) or PUT (often JSON)
            // Check method yourself or trust CI4 routing. Route says POST.

            $user = $this->getUser();

            // Handle JSON vs Form Data
            $input = $this->request->getPost();
            if (empty($input)) {
                $rawInput = $this->request->getJSON(true);
                if (!empty($rawInput))
                    $input = $rawInput;
            }

            if ($user->role === 'admin' && array_key_exists('access_level', $input)) {
                $input['access_level'] = $this->normalizeAccessLevel((string) $input['access_level']);
            } else {
                unset($input['access_level']);
            }

            // Validate
            $validation = \Config\Services::validation();
            $validation->setRules($this->getValidationRules());

            if (!$validation->run($input)) {
                return $this->response->setJSON(['status' => 'error', 'messages' => $validation->getErrors()])->setStatusCode(400);
            }

            $title = trim($input['title'] ?? '');
            $author = trim($input['author'] ?? '');
            $edition = trim($input['edition'] ?? '');
            $isbn = trim($input['isbn_issn'] ?? '');

            $dupError = $this->researchService->checkDuplicate($title, $author, $isbn, $edition, $id);
            if ($dupError) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'messages' => ['duplicate' => $dupError]
                ])->setStatusCode(400);
            }

            $pdfFile = $this->request->getFile('pdf_file');
            $pdfValidationError = $this->validatePdfFile($pdfFile);
            if ($pdfValidationError !== null) {
                return $this->fail($pdfValidationError, 400);
            }

            $this->researchService->updateResearch($id, $user->id, $user->role, $input, $pdfFile);

            // LOG
            log_activity($user->id, $user->name, $user->role, 'UPDATE_RESEARCH', "Updated research ID: $id (" . ($input['title'] ?? '') . ")");

            return $this->respond(['status' => 'success']);
        }
        catch (\Throwable $e) {
            log_message('error', '[Research Update] ' . $e->getMessage());
            if ($e->getCode() == 403)
                return $this->failForbidden();
            return $this->failServerError('An unexpected server error occurred. Please try again later.');
        }
    }

    // 8. APPROVE
    public function approve($id = null)
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $item = $this->researchService->getResearch($id);
        $title = $item ? $item->title : "ID: $id";

        $input = $this->getOptionalJsonPayload();
        $comment = trim((string) ($input['comment'] ?? $this->request->getPost('comment') ?? ''));

        $this->researchService->setStatus($id, 'approved', $user, "🎉 Your research '%s' has been APPROVED!", $comment);

        log_activity($user->id, $user->name, $user->role, 'APPROVE_RESEARCH', "Approved research: $title");

        return $this->respond(['status' => 'success']);
    }

    // 9. REJECT
    public function reject($id = null)
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $item = $this->researchService->getResearch($id);
        $title = $item ? $item->title : "ID: $id";
        
        $input = $this->getOptionalJsonPayload();
        $comment = trim((string) ($input['comment'] ?? $this->request->getPost('comment') ?? ''));

        $this->researchService->setStatus($id, 'rejected', $user, "⚠️ Your research '%s' was returned for revision.", $comment);

        log_activity($user->id, $user->name, $user->role, 'REJECT_RESEARCH', "Rejected research: $title");

        return $this->respond(['status' => 'success']);
    }

    // 10. ARCHIVE
    public function archive($id = null)
    {
        $user = $this->getUser();

        $item = $this->researchService->getResearch($id);
        if (!$item)
            return $this->failNotFound();

        if ($user->role !== 'admin' && (int) $item->uploaded_by !== (int) $user->id) {
            return $this->failForbidden('Access Denied');
        }

        // Prevent repeated actions
        if ($item->status === 'archived') {
            return $this->respond(['status' => 'success', 'message' => 'Already archived']);
        }

        $title = $item->title;

        $this->researchService->setStatus($id, 'archived', $user, "Your research '%s' has been archived.");

        log_activity($user->id, $user->name, $user->role, 'ARCHIVE_RESEARCH', "Archived research: $title");

        return $this->respond(['status' => 'success']);
    }

    // 11. RESTORE
    public function restore($id = null)
    {
        $user = $this->getUser();

        $item = $this->researchService->getResearch($id);
        if (!$item)
            return $this->failNotFound();

        if ($user->role !== 'admin' && (int) $item->uploaded_by !== (int) $user->id) {
            return $this->failForbidden('Access Denied');
        }

        $title = $item->title;
        $restoredStatus = null;

        if ($item->status === 'rejected') {
            if ($user->role !== 'admin') {
                return $this->failForbidden('Access Denied');
            }

            $this->researchService->setStatus((int) $id, 'pending', $user, "Research '%s' restored.");
            $restoredStatus = 'pending';
        } elseif ($item->status === 'archived') {
            $restoredStatus = $this->researchService->restoreArchived((int) $id, $user, "Research '%s' restored.");
        } else {
            return $this->respond([
                'status' => 'success',
                'message' => 'Item does not need restoring',
            ]);
        }

        log_activity($user->id, $user->name, $user->role, 'RESTORE_RESEARCH', "Restored research as {$restoredStatus}: $title");

        return $this->respond([
            'status' => 'success',
            'restored_to' => $restoredStatus,
        ]);
    }

    // 12. DELETE
    public function delete($id = null)
    {
        try {
            $user = $this->getUser();
            if ($user->role !== 'admin') {
                return $this->failForbidden('Access Denied');
            }

            $item = $this->researchService->getResearch((int) $id);
            if (!$item) {
                return $this->failNotFound('Research not found');
            }

            if (!in_array($item->status, ['rejected', 'archived'], true)) {
                return $this->fail('Only rejected or archived research can be permanently deleted.', 400);
            }

            $deletedItem = $this->researchService->purgeResearch((int) $id);

            log_activity(
                $user->id,
                $user->name,
                $user->role,
                'DELETE_RESEARCH',
                "Permanently deleted rejected research: {$deletedItem->title}"
            );

            return $this->respond([
                'status' => 'success',
                'message' => ucfirst((string) $item->status) . ' research deleted permanently.',
            ]);
        }
        catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 400);
        }
        catch (\Throwable $e) {
            log_message('error', '[Research Delete] ' . $e->getMessage());
            return $this->failServerError('Failed to permanently delete the research.');
        }
    }

    // 13. EXTEND DEADLINE
    public function extendDeadline($id = null)
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $newDate = $this->request->getPost('new_deadline');
        if (!$newDate)
            return $this->fail('Date is required.');

        $this->researchService->extendDeadline($id, $newDate, $user->id);
        return $this->respond(['status' => 'success']);
    }

    // 13.1 BULK ACCESS LEVEL UPDATE (Admin only)
    public function bulkAccessLevel()
    {
        try {
            $user = $this->validateUser();
            if (!$user) {
                return $this->failUnauthorized('Access Denied');
            }
            if ($user->role !== 'admin') {
                return $this->failForbidden('Access Denied');
            }

            $input = [];
            try {
                $json = $this->request->getJSON(true);
                if (is_array($json)) {
                    $input = $json;
                }
            } catch (\Throwable $ignored) {
                // Fallback to form payload below.
            }

            if (empty($input)) {
                $input = $this->request->getPost();
            }

            $ids = $input['ids'] ?? null;
            if (!is_array($ids) || empty($ids)) {
                return $this->fail('ids array is required', 400);
            }

            $rawAccessLevel = strtolower(trim((string) ($input['access_level'] ?? '')));
            if (!in_array($rawAccessLevel, ['public', 'private'], true)) {
                return $this->fail('access_level must be either public or private', 400);
            }

            $sanitizedIds = array_values(array_unique(array_map('intval', $ids)));
            $sanitizedIds = array_values(array_filter($sanitizedIds, static fn (int $id): bool => $id > 0));
            if (empty($sanitizedIds)) {
                return $this->fail('No valid research IDs provided', 400);
            }

            if (count($sanitizedIds) > 1000) {
                return $this->fail('Maximum of 1000 IDs per request', 400);
            }

            $result = $this->researchService->bulkUpdateAccessLevel($sanitizedIds, $rawAccessLevel);

            log_activity(
                $user->id,
                $user->name,
                $user->role,
                'BULK_ACCESS_LEVEL_UPDATE',
                'Updated access_level to ' . $rawAccessLevel . ' for ' . $result['updated'] . ' research item(s).'
            );

            return $this->respond([
                'status' => 'success',
                'message' => 'Bulk visibility update completed.',
                'access_level' => $rawAccessLevel,
                'matched' => $result['matched'],
                'updated' => $result['updated'],
            ]);
        }
        catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 400);
        }
        catch (\Throwable $e) {
            log_message('error', '[Bulk Access Level] ' . $e->getMessage());
            return $this->failServerError('An unexpected server error occurred. Please try again later.');
        }
    }

    // 14. COMMENTS
    public function getComments($id = null)
    {
        $user = $this->validateUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        // SECURITY FIX: Prevent reading internal comments on another user's
        // non-approved research by brute-forcing research IDs.
        $research = $this->researchService->getResearch((int) $id);
        $accessError = $this->requireResearchAccess($research, $user);
        if ($accessError !== null) {
            return $accessError;
        }

        $data = $this->researchService->getComments($id);
        return $this->respond($data);
    }

    // 15. ADD COMMENT
    public function addComment()
    {
        $user = $this->validateUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }
        $json = $this->request->getJSON();
        if (!$json || !isset($json->research_id) || !isset($json->comment)) {
            return $this->fail('research_id and comment are required', 400);
        }

        $comment = trim((string) $json->comment);
        if ($comment === '') {
            return $this->fail('Comment cannot be empty', 400);
        }

        $researchId = (int) $json->research_id;
        if ($researchId <= 0) {
            return $this->fail('Invalid research_id', 400);
        }

        // SECURITY FIX: Prevent posting comments on another user's non-approved research.
        $research = $this->researchService->getResearch($researchId);
        $accessError = $this->requireResearchAccess($research, $user);
        if ($accessError !== null) {
            return $accessError;
        }

        $data = [
            'research_id' => $researchId,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role,
            'comment' => $comment
        ];

        if ($this->researchService->addComment($data)) {
            return $this->respondCreated(['status' => 'success']);
        }
        return $this->fail('Failed to save comment');
    }

    // STATS
    public function stats()
    {
        return $this->respond($this->researchService->getStats());
    }

    // MASTERLIST (Admin only - all entries)
    public function masterlist()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden('Access Denied');

        $data = $this->researchService->getAll();
        return $this->respond($data);
    }

    // USER STATS
    public function userStats($userId = null)
    {
        $user = $this->validateUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        if (!$userId)
            return $this->fail('User ID required');

        if ($user->role !== 'admin' && (int) $userId !== (int) $user->id) {
            return $this->failForbidden('Access Denied');
        }

        return $this->respond($this->researchService->getUserStats($userId));
    }

    // CSV IMPORT
    public function importCsv()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin') {
            return $this->failForbidden('Access Denied');
        }

        $file = $this->request->getFile('csv_file');

        if (!$file) {
            return $this->response->setJSON(['message' => 'No CSV file uploaded'])->setStatusCode(400);
        }

        if (!$file->isValid() || $file->getExtension() !== 'csv') {
            return $this->response->setJSON(['message' => 'Invalid or empty CSV file'])->setStatusCode(400);
        }

        try {
            $result = $this->researchService->importCsv($file->getTempName(), (int) $user->id);
            return $this->response->setJSON([
                'status' => 'success',
                'count' => $result['count'],
                'skipped' => $result['skipped'],
                'message' => "Import successful. Added: {$result['count']}. Skipped (Duplicates): {$result['skipped']}."
            ]);
        }
        catch (\Throwable $e) {
            log_message('error', '[Research CSV Import] ' . $e->getMessage());
            return $this->failServerError('An unexpected server error occurred. Please try again later.');
        }
    }

    // SINGLE ROW IMPORT (For Sequential Processing)
    public function importSingle()
    {
        try {
            $user = $this->validateUser();
            if (!$user)
                return $this->failUnauthorized('Access Denied');
            if ($user->role !== 'admin')
                return $this->failForbidden('Access Denied');

            $input = $this->request->getJSON(true);
            if (empty($input)) {
                return $this->fail('No data provided', 400);
            }

            // Security: Use Logged In User
            $result = $this->researchService->importSingleRow($input, $user->id);

            if ($result['status'] === 'success') {
                log_activity($user->id, $user->name, $user->role, 'IMPORT_SINGLE', "Imported single research: " . ($input['Title'] ?? 'Untitled'));
                return $this->respond(['status' => 'success', 'id' => $result['id']]);
            }
            else {
                return $this->respond(['status' => 'skipped', 'message' => $result['message']]);
            }

        }
        catch (\Throwable $e) {
            log_message('error', '[Import Single] ' . $e->getMessage());
            return $this->failServerError('An unexpected server error occurred. Please try again later.');
        }
    }

    // BULK PDF UPLOAD
    public function uploadBulkPdfs()
    {
        try {
            $user = $this->validateUser();
            if (!$user)
                return $this->failUnauthorized('Access Denied');
            if ($user->role !== 'admin')
                return $this->failForbidden('Access Denied');

            $files = $this->request->getFiles();

            // CI4 structure: if input is 'pdf_files[]', getFiles() returns array or object structure.
            // We expect 'pdf_files'
            if (!$files || !isset($files['pdf_files'])) {
                return $this->fail('No files uploaded', 400);
            }

            $pdfFiles = $files['pdf_files'];
            // If single file uploaded, CI4 might allow it not as array? Ensure iterable.
            if (!is_array($pdfFiles)) {
                $pdfFiles = [$pdfFiles];
            }

            if (count($pdfFiles) > 10) {
                return $this->fail('Maximum of 10 files allowed per upload', 400);
            }

            // Optional per-file manual record IDs and ISBN/edition hints.
            // Index corresponds to the order of pdf_files[].
            $manualResearchIds = $this->request->getPost('pdf_research_id') ?? [];
            $isbnHints    = $this->request->getPost('pdf_isbn')    ?? [];
            $editionHints = $this->request->getPost('pdf_edition') ?? [];
            if (!is_array($manualResearchIds)) $manualResearchIds = [];
            if (!is_array($isbnHints))    $isbnHints    = [];
            if (!is_array($editionHints)) $editionHints = [];

            $matched = 0;
            $skipped = 0;
            $details = [];

            foreach ($pdfFiles as $idx => $file) {
                $originalName = (string) $file->getClientName();
                $pdfValidationError = $this->validatePdfFile($file, true);
                if ($pdfValidationError !== null) {
                    $skipped++;
                    $details[] = "Skipped: $originalName ($pdfValidationError)";
                    continue;
                }

                if ($file->hasMoved()) {
                    $skipped++;
                    $details[] = "Skipped: $originalName (File already moved)";
                    continue;
                }

                // Filename without extension is the primary title candidate
                $titleCandidate = pathinfo($originalName, PATHINFO_FILENAME);

                $manualResearchId = (int) ($manualResearchIds[$idx] ?? 0);
                if ($manualResearchId > 0) {
                    $resultStatus = $this->researchService->attachPdfToResearchId($manualResearchId, $file);
                } else {
                    // Per-file hints (optional, sent alongside files from frontend)
                    $isbnHint    = trim((string) ($isbnHints[$idx]    ?? ''));
                    $editionHint = trim((string) ($editionHints[$idx] ?? ''));

                    // Call Service to find and attach (service also auto-parses bracket hints)
                    $resultStatus = $this->researchService->matchAndAttachPdf(
                        $titleCandidate, $file, $isbnHint, $editionHint
                    );
                }

                if ($resultStatus === 'linked') {
                    $matched++;
                    $details[] = $manualResearchId > 0
                        ? "Linked manually: $originalName to record ID $manualResearchId"
                        : "Linked: $originalName";
                }
                elseif ($resultStatus === 'exists') {
                    $skipped++;
                    $details[] = $manualResearchId > 0
                        ? "Skipped: $originalName (Selected record already has a file)"
                        : "Skipped: $originalName (Record already has a file)";
                }
                elseif ($resultStatus === 'no_match') {
                    $skipped++;
                    $details[] = $manualResearchId > 0
                        ? "Skipped: $originalName (Selected record was not found)"
                        : "Skipped: $originalName (No matching record found; check title, ISBN, or edition)";
                }
                else {
                    $skipped++;
                    $details[] = "Skipped: $originalName (Encryption/storage error)";
                }
            }

            // LOG IT
            $logDetails = "Bulk Upload: Checked " . count($pdfFiles) . " files. Linked: $matched. Skipped: $skipped.";
            log_activity($user->id, $user->name, $user->role, 'BULK_UPLOAD_PDF', $logDetails);

            return $this->response->setJSON([
                'status' => 'success',
                'matched' => $matched,
                'skipped' => $skipped,
                'message' => "Bulk Upload Complete. Linked: $matched. Skipped: $skipped.",
                'details' => $details
            ]);
        }
        catch (\Throwable $e) {
            log_message('error', '[Bulk Upload] ' . $e->getMessage());
            return $this->failServerError('An unexpected server error occurred. Please try again later.');
        }
    }

    /**
     * Preview matched records for bulk PDF upload before actual file upload.
     * Expects JSON containing an array of 'files' with { filename, isbnHint, editionHint }.
     */
    public function previewBulkPdfs()
    {
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');
        if ($user->role !== 'admin') return $this->failForbidden('Access Denied');

        $reqData = $this->request->getJSON(true);
        $files = $reqData['files'] ?? [];

        if (!is_array($files)) {
            return $this->failValidationErrors('Invalid input format. Expected an array of files.');
        }

        try {
            $results = $this->researchService->previewPdfMatches($files);
            return $this->response->setJSON([
                'status' => 'success',
                'preview' => $results,
                'unlinked_records' => $this->researchService->getRecordsMissingPdf()
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Preview Bulk PDF] ' . $e->getMessage());
            return $this->failServerError('An unexpected server error occurred. Please try again later.');
        }
    }

    /**
     * Previews CSV rows to detect duplicates before uploading
     */
    public function previewCsv()
    {
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');
        if ($user->role !== 'admin') return $this->failForbidden('Access Denied');

        $reqData = $this->request->getJSON(true);
        $rows = $reqData['rows'] ?? [];

        if (!is_array($rows)) {
            return $this->failValidationErrors('Invalid input format.');
        }

        try {
            $results = $this->researchService->previewCsvDuplicates($rows);
            return $this->response->setJSON([
                'status' => 'success',
                'preview' => $results
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Preview CSV] ' . $e->getMessage());
            return $this->failServerError('An unexpected server error occurred. Please try again later.');
        }
    }
}
