<?php

namespace App\Services;

use App\Models\UserModel;

class UserService extends BaseService
{
    protected $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
    }

    public function getAllUsers($role = null)
    {
        if ($role) {
            return $this->userModel->where('role', $role)
                          ->select('id, name, email, role, created_at')
                          ->findAll();
        }
        
        return $this->userModel->select('id, name, email, role, created_at')->findAll();
    }
}
