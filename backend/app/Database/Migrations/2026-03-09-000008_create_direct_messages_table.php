<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDirectMessagesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('direct_messages')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'sender_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'recipient_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'is_read' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('sender_id');
        $this->forge->addKey('recipient_id');
        $this->forge->addKey('is_read');
        $this->forge->addKey('created_at');
        $this->forge->createTable('direct_messages', true);
    }

    public function down()
    {
        if ($this->db->tableExists('direct_messages')) {
            $this->forge->dropTable('direct_messages', true);
        }
    }
}
