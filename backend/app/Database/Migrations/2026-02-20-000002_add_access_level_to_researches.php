<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAccessLevelToResearches extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('researches')) {
            return;
        }

        if (!$this->db->fieldExists('access_level', 'researches')) {
            $this->forge->addColumn('researches', [
                'access_level' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'public',
                    'null' => false,
                    'after' => 'status',
                ],
            ]);
        }

        $this->db->table('researches')
            ->where('access_level', null)
            ->orWhere('access_level', '')
            ->set(['access_level' => 'public'])
            ->update();
    }

    public function down()
    {
        if ($this->db->tableExists('researches') && $this->db->fieldExists('access_level', 'researches')) {
            $this->forge->dropColumn('researches', 'access_level');
        }
    }
}
