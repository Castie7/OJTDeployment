<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SetDefaultResearchAccessPrivate extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('researches') || !$this->db->fieldExists('access_level', 'researches')) {
            return;
        }

        $this->forge->modifyColumn('researches', [
            'access_level' => [
                'name' => 'access_level',
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'private',
                'null' => false,
            ],
        ]);

        $this->db->table('researches')
            ->groupStart()
                ->where('access_level', null)
                ->orWhere('access_level', '')
            ->groupEnd()
            ->set(['access_level' => 'private'])
            ->update();
    }

    public function down()
    {
        if (!$this->db->tableExists('researches') || !$this->db->fieldExists('access_level', 'researches')) {
            return;
        }

        $this->forge->modifyColumn('researches', [
            'access_level' => [
                'name' => 'access_level',
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'public',
                'null' => false,
            ],
        ]);
    }
}

