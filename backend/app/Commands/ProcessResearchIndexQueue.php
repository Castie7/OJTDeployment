<?php

namespace App\Commands;

use App\Services\ResearchService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ProcessResearchIndexQueue extends BaseCommand
{
    protected $group = 'Search';
    protected $name = 'index:research';
    protected $description = 'Process pending research search-index jobs with retry support.';
    protected $usage = 'index:research [--limit <number>]';
    protected $options = [
        '--limit' => 'Maximum jobs to process in one run (default: 20, max: 200).',
    ];

    public function run(array $params)
    {
        $limitRaw = CLI::getOption('limit') ?? ($params[0] ?? 20);
        if (!is_numeric((string) $limitRaw)) {
            CLI::error('Invalid limit. Use a numeric value.');
            return;
        }

        $limit = max(1, min(200, (int) $limitRaw));

        $service = new ResearchService();
        $result = $service->processPendingIndexJobs($limit);

        CLI::write('Research indexing queue run complete.', 'green');
        CLI::write('Processed: ' . (int) ($result['processed'] ?? 0));
        CLI::write('Completed: ' . (int) ($result['completed'] ?? 0));
        CLI::write('Requeued: ' . (int) ($result['requeued'] ?? 0));
        CLI::write('Failed: ' . (int) ($result['failed'] ?? 0));
    }
}

