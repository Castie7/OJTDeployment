<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserStorageFilesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('user_storage_files')) {
            return;
        }

        $usersIdUnsigned = $this->isUsersIdUnsigned();

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
                'unsigned' => $usersIdUnsigned,
            ],
            'original_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'stored_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'size_bytes' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'default' => 0,
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
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_storage_files', true);
    }

    public function down()
    {
        if ($this->db->tableExists('user_storage_files')) {
            $this->forge->dropTable('user_storage_files', true);
        }
    }

    private function isUsersIdUnsigned(): bool
    {
        if (!$this->db->tableExists('users')) {
            return false;
        }

        $column = $this->db->query("SHOW COLUMNS FROM `users` LIKE 'id'")->getRowArray();
        if (!is_array($column)) {
            return false;
        }

        $columnType = strtolower((string) ($column['Type'] ?? ''));
        return str_contains($columnType, 'unsigned');
    }
}
