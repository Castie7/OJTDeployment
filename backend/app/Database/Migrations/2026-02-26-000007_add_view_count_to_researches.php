<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddViewCountToResearches extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('researches')) {
            return;
        }

        if (!$this->db->fieldExists('view_count', 'researches')) {
            $this->forge->addColumn('researches', [
                'view_count' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'default' => 0,
                    'null' => false,
                    'after' => 'access_level',
                ],
            ]);
        }

        $this->db->table('researches')
            ->where('view_count', null)
            ->set(['view_count' => 0])
            ->update();
    }

    public function down()
    {
        if ($this->db->tableExists('researches') && $this->db->fieldExists('view_count', 'researches')) {
            $this->forge->dropColumn('researches', 'view_count');
        }
    }
}

