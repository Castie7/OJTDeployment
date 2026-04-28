<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSearchTextToResearchDetails extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('research_details')) {
            return;
        }

        if (!$this->db->fieldExists('search_text', 'research_details')) {
            $this->forge->addColumn('research_details', [
                'search_text' => [
                    'type' => 'MEDIUMTEXT',
                    'null' => true,
                    'after' => 'link',
                ],
            ]);
        }

        $this->db->query(
            "UPDATE research_details
             SET search_text = TRIM(CONCAT_WS(' ', knowledge_type, publisher, isbn_issn, subjects, physical_description))
             WHERE search_text IS NULL OR search_text = ''"
        );
    }

    public function down()
    {
        if ($this->db->tableExists('research_details') && $this->db->fieldExists('search_text', 'research_details')) {
            $this->forge->dropColumn('research_details', 'search_text');
        }
    }
}

