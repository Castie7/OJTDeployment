<?php

namespace App\Database\Seeds;

use App\Services\ResearchService;
use CodeIgniter\Database\Seeder;

class ReindexResearchSearchSeeder extends Seeder
{
    public function run()
    {
        if (!$this->db->tableExists('researches')) {
            echo "Reindex skipped: 'researches' table not found.\n";
            return;
        }

        $rows = $this->db->table('researches')->select('id')->get()->getResultArray();
        $service = new ResearchService();

        $count = 0;
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $service->refreshSearchIndex($id);
            $count++;
        }

        echo "Reindex complete. Indexed {$count} research item(s).\n";
    }
}

