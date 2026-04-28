<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsDisabledToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'is_disabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'must_change_password',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'is_disabled');
    }
}
