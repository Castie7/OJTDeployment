<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFolderSupportToUserStorageFiles extends Migration
{
    private const TABLE = 'user_storage_files';
    private const INDEX_NAME = 'idx_user_storage_user_folder_item';

    public function up()
    {
        if (!$this->db->tableExists(self::TABLE)) {
            return;
        }

        $newFields = [];

        if (!$this->db->fieldExists('item_type', self::TABLE)) {
            $newFields['item_type'] = [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'file',
                'after' => 'user_id',
            ];
        }

        if (!$this->db->fieldExists('folder_path', self::TABLE)) {
            $newFields['folder_path'] = [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'default' => '/',
                'after' => 'item_type',
            ];
        }

        if ($newFields !== []) {
            $this->forge->addColumn(self::TABLE, $newFields);
        }

        $this->db->query("UPDATE `" . self::TABLE . "` SET `item_type` = 'file' WHERE `item_type` IS NULL OR `item_type` = ''");
        $this->db->query("UPDATE `" . self::TABLE . "` SET `folder_path` = '/' WHERE `folder_path` IS NULL OR `folder_path` = ''");

        if (!$this->indexExists(self::INDEX_NAME)) {
            $this->db->query(
                "CREATE INDEX `" . self::INDEX_NAME . "` ON `" . self::TABLE . "` (`user_id`, `folder_path`, `item_type`)"
            );
        }
    }

    public function down()
    {
        if (!$this->db->tableExists(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::INDEX_NAME)) {
            $this->db->query("DROP INDEX `" . self::INDEX_NAME . "` ON `" . self::TABLE . "`");
        }

        $dropFields = [];
        if ($this->db->fieldExists('folder_path', self::TABLE)) {
            $dropFields[] = 'folder_path';
        }
        if ($this->db->fieldExists('item_type', self::TABLE)) {
            $dropFields[] = 'item_type';
        }

        if ($dropFields !== []) {
            $this->forge->dropColumn(self::TABLE, $dropFields);
        }
    }

    private function indexExists(string $indexName): bool
    {
        $result = $this->db
            ->query("SHOW INDEX FROM `" . self::TABLE . "` WHERE Key_name = ?", [$indexName])
            ->getResultArray();

        return $result !== [];
    }
}
