<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\NotificationService;
use App\Services\AuthService;

class NotificationController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $notifService;
    protected $authService;

    public function __construct() {
        $this->notifService = new NotificationService();
        $this->authService = new AuthService();
    }

    private function validateUser()
    {
        $token = $this->request->getHeaderLine('Authorization');
        return $this->authService->validateUser($token);
    }

    public function index()
    {
        $user = $this->validateUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        try {
            $data = $this->notifService->getUserNotifications($user->id);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function markAsRead()
    {
        $user = $this->validateUser();
        if (!$user) {
            return $this->failUnauthorized('Access Denied');
        }

        try {
            $this->notifService->markAsRead($user->id);
            return $this->respond(['success' => true]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}
