<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class EmergencyResetSeeder extends Seeder
{
    public function run()
    {
        if (!$this->db->tableExists('users')) {
            echo "EmergencyResetSeeder skipped: 'users' table not found.\n";
            return;
        }

        $superAdmin = [
            'name' => trim((string) env('reset.superadmin.name', 'Super Admin')),
            'email' => strtolower(trim((string) env('reset.superadmin.email', 'admin@bsu.edu.ph'))),
            'password' => (string) env('reset.superadmin.password', '123admin'),
            'role' => 'admin',
        ];

        $resetAccounts = [$superAdmin];
        $now = date('Y-m-d H:i:s');
        $created = 0;
        $reset = 0;

        foreach ($resetAccounts as $account) {
            if ($account['email'] === '') {
                continue;
            }

            $existing = $this->db->table('users')
                ->select('id')
                ->where('email', $account['email'])
                ->get()
                ->getRowArray();

            $data = [
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => password_hash($account['password'], PASSWORD_DEFAULT),
                'role' => $account['role'],
                'auth_token' => null,
                'updated_at' => $now,
            ];

            if ($existing) {
                $this->db->table('users')
                    ->where('id', (int) $existing['id'])
                    ->update($data);
                $reset++;
                echo "Reset account: {$account['email']}\n";
                continue;
            }

            $data['created_at'] = $now;
            $this->db->table('users')->insert($data);
            $created++;
            echo "Created and reset account: {$account['email']}\n";
        }

        if ($this->db->tableExists('ci_sessions')) {
            $this->db->table('ci_sessions')->emptyTable();
            echo "Cleared all sessions.\n";
        }

        echo "Emergency reset complete. Reset: {$reset}, created: {$created}.\n";
        echo "Default reset credentials (override via .env reset.superadmin.* keys):\n";
        echo "- {$superAdmin['email']} / {$superAdmin['password']}\n";
        echo "Change these passwords immediately after login.\n";
    }
}
