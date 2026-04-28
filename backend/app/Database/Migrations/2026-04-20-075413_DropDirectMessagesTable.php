<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropDirectMessagesTable extends Migration
{
    public function up()
    {
        $this->forge->dropTable('direct_messages', true);
    }

    public function down()
    {
        // No going back securely, data is lost
    }
}
