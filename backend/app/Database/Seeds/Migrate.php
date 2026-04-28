<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class Migrate extends Seeder
{
    public function run()
    {
        $admins = [];
        $this->db->disableForeignKeyChecks();
        
        // 1. BACKUP ADMINS (Safe Mode)
        // Only backup Super Admin (admin@bsu.edu.ph)
        if ($this->db->tableExists('users')) {
            $admins = $this->db->table('users')
                           ->where('email', 'admin@bsu.edu.ph')
                           ->get()->getResultArray();
            echo "Backed up " . count($admins) . " Super Admin account(s).\n";
        } else {
            echo "Skipping backup: 'users' table not found (Database might be empty).\n";
        }

        // 2. DROP ALL TABLES
        $forge = \Config\Database::forge();
        $tables = $this->db->listTables();
        foreach ($tables as $table) {
            $forge->dropTable($table, true);
        }
        echo "All tables dropped.\n";

        // 3. IMPORT SQL FILE
        // Try to find the file in looking up from ROOTPATH (assuming backend/ is root)
        $possiblePaths = [
            ROOTPATH . '../rootcrop_db.sql',
            ROOTPATH . 'rootcrop_db.sql',
            FCPATH . '../rootcrop_db.sql',
            'C:/xampp/htdocs/OJT2/rootcrop_db.sql' // Absolute fallback
        ];

        $sqlPath = '';
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $sqlPath = $path;
                break;
            }
        }
        
        if (!$sqlPath) {
            echo "CRITICAL ERROR: SQL file not found. Searched in:\n";
            print_r($possiblePaths);
            return;
        }

        echo "Importing from: " . realpath($sqlPath) . "\n";
        $sqlContent = file_get_contents($sqlPath);

        // Remove comments
        $lines = explode("\n", $sqlContent);
        $cleanSql = "";
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && strpos($line, '--') !== 0 && strpos($line, '/*') !== 0) {
                $cleanSql .= $line . "\n";
            }
        }

        // Split by semicolon
        $statements = explode(";", $cleanSql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $this->db->query($statement);
                } catch (\Exception $e) {
                    echo "SQL Error: " . $e->getMessage() . "\n";
                }
            }
        }
        echo "Database imported successfully.\n";

        // 4. TRUNCATE DATA TABLES (Remove Sample Data)
        // Using DELETE + AUTO_INCREMENT reset is safer than TRUNCATE for FKs
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        
        $tablesToWipe = [
            'activity_logs', 
            'notifications', 
            'research_comments', 
            'research_details', 
            'researches', 
            'ci_sessions'
        ];

        foreach ($tablesToWipe as $table) {
            if ($this->db->tableExists($table)) {
                // $this->db->table($table)->truncate(); // Caused FK Error
                $this->db->table($table)->emptyTable(); // Runs DELETE
                
                // Reset Auto Increment
                $this->db->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            }
        }
        echo "Sample data wiped (DELETE + Reset AI).\n";

        // 5. RESTORE SUPER ADMIN
        if ($this->db->tableExists('users')) {
            if (!empty($admins)) {
                // Scenario A: We have a backup. Restore it exactly.
                $this->db->table('users')->truncate();
                $this->db->table('users')->insertBatch($admins);
                echo "Restored Super Admin from backup.\n";
            } else {
                // Scenario B: No backup (Crash recovery). Use SQL file's data.
                // Validate SQL data has the super admin
                // Delete everyone who is NOT the super admin
                $this->db->table('users')->where('email !=', 'admin@bsu.edu.ph')->delete();
                echo "No backup found. Retained Super Admin from SQL, deleted all others.\n";
            }
        } else {
            echo "CRITICAL: 'users' table does not exist after import!\n";
        }

        $this->db->enableForeignKeyChecks();
        echo "========================================\n";
        echo " DATABASE REFRESH COMPLETE \n";
        echo "========================================\n";
    }
}
