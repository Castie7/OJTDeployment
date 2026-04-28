<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('welcome_message');
    }

    public function testApi()
    {
        // ❌ REMOVED: Manual CORS headers and OPTIONS check.
        // The global App\Filters\Cors.php handles the permission check safely.

        $data = [
            'status' => 'success',
            'message' => 'Hello from CodeIgniter 4 Backend!',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $this->response->setJSON($data);
    }
}