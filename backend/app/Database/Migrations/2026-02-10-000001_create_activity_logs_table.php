<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true, // Nullable for guest actions (e.g. failed login)
            ],
            'user_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'default'    => 'Guest',
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => '100', // e.g., 'LOGIN', 'CREATE_RESEARCH'
            ],
            'details' => [
                'type' => 'TEXT', // JSON or plain text description
                'null' => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id'); // Index for faster filtering
        $this->forge->addKey('action');
        $this->forge->createTable('activity_logs');
    }

    public function down()
    {
        $this->forge->dropTable('activity_logs');
    }
}
