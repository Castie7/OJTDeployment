<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRecycleBinSupportToUserStorageFiles extends Migration
{
    private const TABLE = 'user_storage_files';
    private const INDEX_NAME = 'idx_user_storage_user_deleted_at';

    public function up()
    {
        if (!$this->db->tableExists(self::TABLE)) {
            return;
        }

        if (!$this->db->fieldExists('deleted_at', self::TABLE)) {
            $this->forge->addColumn(self::TABLE, [
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'updated_at',
                ],
            ]);
        }

        if (!$this->indexExists(self::INDEX_NAME)) {
            $this->db->query(
                "CREATE INDEX `" . self::INDEX_NAME . "` ON `" . self::TABLE . "` (`user_id`, `deleted_at`)"
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

        if ($this->db->fieldExists('deleted_at', self::TABLE)) {
            $this->forge->dropColumn(self::TABLE, 'deleted_at');
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
