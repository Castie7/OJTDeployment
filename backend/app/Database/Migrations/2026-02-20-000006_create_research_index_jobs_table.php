<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResearchIndexJobsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('research_index_jobs')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'research_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'pending',
            ],
            'reason' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'attempt_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ],
            'max_attempts' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 3,
            ],
            'priority' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 100,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'next_retry_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('research_id');
        $this->forge->addKey('status');
        $this->forge->addKey('priority');
        $this->forge->addKey('next_retry_at');
        $this->forge->createTable('research_index_jobs', true);
    }

    public function down()
    {
        if ($this->db->tableExists('research_index_jobs')) {
            $this->forge->dropTable('research_index_jobs', true);
        }
    }
}

