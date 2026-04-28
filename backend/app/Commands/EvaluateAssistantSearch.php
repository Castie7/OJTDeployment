<?php

namespace App\Commands;

use App\Services\ResearchService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class EvaluateAssistantSearch extends BaseCommand
{
    protected $group = 'Search';
    protected $name = 'assistant:evaluate';
    protected $description = 'Run local benchmark queries against the research assistant search pipeline.';
    protected $usage = 'assistant:evaluate [--limit <number>] [--file <path>]';
    protected $options = [
        '--limit' => 'Max results fetched per query (default: 20, max: 50).',
        '--file' => 'Optional PHP file returning evaluation query cases.',
    ];

    public function run(array $params)
    {
        $datasetPath = (string) (CLI::getOption('file') ?? APPPATH . 'Data/assistant_eval_queries.php');
        if (!is_file($datasetPath)) {
            CLI::error('Evaluation dataset not found: ' . $datasetPath);
            return;
        }

        $cases = require $datasetPath;
        if (!is_array($cases) || empty($cases)) {
            CLI::error('Evaluation dataset must return a non-empty array.');
            return;
        }

        $limitRaw = CLI::getOption('limit') ?? 20;
        if (!is_numeric((string) $limitRaw)) {
            CLI::error('Invalid limit. Use a numeric value.');
            return;
        }
        $limit = max(1, min(50, (int) $limitRaw));

        $service = new ResearchService();

        $total = 0;
        $passed = 0;

        CLI::write('Running assistant evaluation with ' . count($cases) . ' case(s)...', 'yellow');
        CLI::newLine();

        foreach ($cases as $index => $case) {
            if (!is_array($case)) {
                continue;
            }

            $query = trim((string) ($case['query'] ?? ''));
            if ($query === '') {
                continue;
            }

            $total++;
            $label = trim((string) ($case['name'] ?? ('Case #' . ($index + 1))));
            $strict = !empty($case['strict']);
            $minResults = max(0, (int) ($case['min_results'] ?? 1));

            $expectAny = [];
            $rawExpectAny = $case['expect_any'] ?? [];
            if (is_array($rawExpectAny)) {
                foreach ($rawExpectAny as $term) {
                    $termText = trim((string) $term);
                    if ($termText !== '') {
                        $expectAny[] = mb_strtolower($termText);
                    }
                }
            }

            $results = $service->getAllApproved(
                null,
                null,
                true,
                $query,
                $strict,
                $limit
            );

            $resultCount = is_array($results) ? count($results) : 0;
            $countPass = $resultCount >= $minResults;

            $semanticPass = true;
            if (!empty($expectAny)) {
                $semanticPass = false;
                foreach ($results as $row) {
                    if (!is_array($row) && !is_object($row)) {
                        continue;
                    }

                    $title = (string) (is_array($row) ? ($row['title'] ?? '') : ($row->title ?? ''));
                    $author = (string) (is_array($row) ? ($row['author'] ?? '') : ($row->author ?? ''));
                    $subjects = (string) (is_array($row) ? ($row['subjects'] ?? '') : ($row->subjects ?? ''));
                    $publisher = (string) (is_array($row) ? ($row['publisher'] ?? '') : ($row->publisher ?? ''));
                    $knowledgeType = (string) (is_array($row) ? ($row['knowledge_type'] ?? '') : ($row->knowledge_type ?? ''));
                    $isbnIssn = (string) (is_array($row) ? ($row['isbn_issn'] ?? '') : ($row->isbn_issn ?? ''));

                    $haystack = mb_strtolower(trim(implode(' ', [
                        $title,
                        $author,
                        $subjects,
                        $publisher,
                        $knowledgeType,
                        $isbnIssn,
                    ])));

                    foreach ($expectAny as $term) {
                        if ($term !== '' && str_contains($haystack, $term)) {
                            $semanticPass = true;
                            break 2;
                        }
                    }
                }
            }

            $casePass = $countPass && $semanticPass;
            if ($casePass) {
                $passed++;
            }

            $status = $casePass ? '[PASS]' : '[FAIL]';
            $mode = $strict ? 'specific' : 'broad';
            CLI::write($status . ' ' . $label . ' | mode=' . $mode . ' | results=' . $resultCount . ' | query="' . $query . '"');
        }

        CLI::newLine();
        CLI::write('Summary: ' . $passed . '/' . $total . ' case(s) passed.', $passed === $total ? 'green' : 'red');
    }
}

