<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run()
    {
        if (!$this->db->tableExists('users')) {
            echo "UsersSeeder skipped: 'users' table not found. Run migrations first.\n";
            return;
        }

        $superAdmin = [
            'name' => trim((string) env('seed.superadmin.name', 'Super Admin')),
            'email' => strtolower(trim((string) env('seed.superadmin.email', 'admin@bsu.edu.ph'))),
            'password' => (string) env('seed.superadmin.password', 'ChangeMe123!'),
            'role' => 'admin',
        ];

        $defaultUser = [
            'name' => trim((string) env('seed.user.name', 'Research User')),
            'email' => strtolower(trim((string) env('seed.user.email', 'researcher@bsu.edu.ph'))),
            'password' => (string) env('seed.user.password', 'ChangeMe123!'),
            'role' => 'user',
        ];

        $seedUsers = [$superAdmin, $defaultUser];

        $created = 0;
        $updatedRole = 0;

        foreach ($seedUsers as $seedUser) {
            if ($seedUser['email'] === '') {
                continue;
            }

            $existing = $this->db->table('users')
                ->select('id, role')
                ->where('email', $seedUser['email'])
                ->get()
                ->getRowArray();

            if ($existing) {
                if (($existing['role'] ?? 'user') !== $seedUser['role']) {
                    $this->db->table('users')
                        ->where('id', (int) $existing['id'])
                        ->update([
                            'role' => $seedUser['role'],
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    $updatedRole++;
                    echo "Updated role for existing user: {$seedUser['email']} -> {$seedUser['role']}\n";
                } else {
                    echo "User already exists: {$seedUser['email']}\n";
                }
                continue;
            }

            $this->db->table('users')->insert([
                'name' => $seedUser['name'],
                'email' => $seedUser['email'],
                'password' => password_hash($seedUser['password'], PASSWORD_DEFAULT),
                'role' => $seedUser['role'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $created++;
            echo "Created user: {$seedUser['email']} ({$seedUser['role']})\n";
        }

        echo "UsersSeeder complete. Created: {$created}, role updates: {$updatedRole}.\n";
        echo "If defaults were used, change seeded passwords immediately.\n";
    }
}

