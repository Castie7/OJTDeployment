<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssistantSearchLogsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('assistant_search_logs')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'query' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'effective_query' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'mode' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'broad',
            ],
            'result_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ],
            'top_research_ids' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'latency_ms' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ],
            'confidence' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'null' => true,
            ],
            'is_strong_match' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'feedback' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'feedback_note' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'user_agent' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
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
        $this->forge->addKey('user_id');
        $this->forge->addKey('created_at');
        $this->forge->addKey('feedback');
        $this->forge->addKey('latency_ms');
        $this->forge->createTable('assistant_search_logs', true);
    }

    public function down()
    {
        if ($this->db->tableExists('assistant_search_logs')) {
            $this->forge->dropTable('assistant_search_logs', true);
        }
    }
}
