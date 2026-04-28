<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropUserStorageFilesTable extends Migration
{
    public function up()
    {
        $this->forge->dropTable('user_storage_files', true);
    }

    public function down()
    {
        // Not implemented
    }
}
