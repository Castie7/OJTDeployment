<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ResearchModel;

use App\Services\EncryptionService;

class MigrateEncryption extends BaseCommand
{
    protected $group       = 'Encryption';
    protected $name        = 'encryption:migrate';
    protected $description = 'Encrypts all existing plain-text files in public/uploads and writable/uploads/storage.';

    public function run(array $params)
    {
        CLI::write("Starting Encryption Migration...", 'yellow');
        $encService = new EncryptionService();
        $this->migrateResearchFiles($encService);

        CLI::write("Migration complete!", 'green');
    }

    private function migrateResearchFiles(EncryptionService $enc)
    {
        CLI::write("Migrating Research Files (public/uploads -> writable/uploads/research)...", 'yellow');
        
        $model = new ResearchModel();
        $items = $model->where('file_path !=', null)->where('file_path !=', '')->findAll();
        
        $sourceDir = ROOTPATH . 'public/uploads';
        $targetDir = WRITEPATH . 'uploads/research';
        
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $success = 0; $skipped = 0; $failed = 0;
        foreach ($items as $item) {
            $fileName = basename($item->file_path);
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $fileName;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
            
            if (!is_file($sourcePath)) {
                $skipped++;
                continue;
            }
            
            try {
                $enc->encryptFile($sourcePath, $targetPath);
                unlink($sourcePath); // Delete plain text file
                $success++;
                CLI::write("   [OK] Encrypted & moved: $fileName", 'green');
            } catch (\Throwable $e) {
                $failed++;
                CLI::error("   [FAILED] $fileName: " . $e->getMessage());
            }
        }
        
        CLI::write("Research Migration Done. Success: $success, Skipped: $skipped, Failed: $failed", 'cyan');
    }


}
