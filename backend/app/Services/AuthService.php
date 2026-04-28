<?php

namespace App\Services;

use App\Models\UserModel;
use CodeIgniter\Config\Services;

class AuthService extends BaseService
{
    protected $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
        helper('cookie');
    }

    public function login(string $email, string $password): array
    {
        $user = $this->userModel->where('email', $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            return ['status' => 'invalid', 'user' => null];
        }

        if ((bool) ($user->is_disabled ?? false)) {
            return ['status' => 'disabled', 'user' => $user];
        }

        // Set Session
        $sessionData = [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'isLoggedIn' => true,
        ];
        session()->set($sessionData);

        return ['status' => 'success', 'user' => $user];
    }

    public function logout()
    {
        session()->destroy();
        
        $sessionName = config('Session')->cookieName;
        $csrfName    = config('Security')->cookieName;

        delete_cookie($sessionName);
        delete_cookie($csrfName);
    }

    public function verifySession()
    {
        if (!session()->get('isLoggedIn')) {
            return null;
        }

        $user = $this->userModel->find(session()->get('id'));
        if (!$user || (bool) ($user->is_disabled ?? false)) {
            $this->logout();
            return null;
        }

        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'role'  => $user->role,
            'email' => $user->email,
        ];
    }
    
    public function validateUser($token = null)
    {
        // Session-based authentication only.
        if (session()->get('isLoggedIn')) {
            $user = $this->userModel->find(session()->get('id'));
            if (!$user || (bool) ($user->is_disabled ?? false)) {
                $this->logout();
                return false;
            }

            return $user; // Returns Entity
        }

        return false;
    }
    
    /**
     * Updates a user's profile.
     * Returns the updated user entity on success, or throws an exception on failure/validation error.
     */
    public function updateProfile(int $userId, object $data, int $currentUserId, string $currentUserRole)
    {
        // Security check
        if ($currentUserId != $userId && $currentUserRole !== 'admin') {
            throw new \Exception('You are not allowed to edit this profile.', 403);
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        $dataToUpdate = [];

        if (isset($data->name) && !empty(trim($data->name))) {
            $dataToUpdate['name'] = trim($data->name);
        }
        if (isset($data->email) && !empty(trim($data->email))) {
            $dataToUpdate['email'] = trim($data->email);
        }

        // Password change logic
        if (!empty($data->new_password)) {
            if (empty($data->current_password)) {
                 throw new \Exception('To change password, you must enter your Current Password.');
            }
            if (!password_verify($data->current_password, $user->password)) {
                 throw new \Exception('Incorrect Current Password.');
            }
            $dataToUpdate['password'] = password_hash($data->new_password, PASSWORD_DEFAULT);
            $dataToUpdate['must_change_password'] = 0; // Clear the forced reset flag
        }

        if (empty($dataToUpdate)) {
             throw new \Exception('No changes were provided.');
        }

        if ($this->userModel->update($userId, $dataToUpdate)) {
             $updatedUser = $this->userModel->find($userId);
             // Make sure we don't return the password hash
             unset($updatedUser->password);
             
             // Update Session if we changed our own name/email
             if ($currentUserId == $userId) {
                 session()->set([
                     'name' => $updatedUser->name,
                     'email' => $updatedUser->email
                 ]);
             }
             return $updatedUser;
        }
        
        throw new \Exception('Database update failed');
    }

    public function register(object $data)
    {
         if (!isset($data->email) || !isset($data->password) || !isset($data->name)) {
             throw new \Exception('Missing required fields (name, email, password)', 400);
         }

         if ($this->userModel->where('email', $data->email)->first()) {
             throw new \Exception('Email already in use', 409);
         }

         $role = $data->role ?? 'user';
         if (!in_array($role, ['user', 'admin'], true)) {
             $role = 'user';
         }

         $newUser = new \App\Entities\User();
         $newUser->name     = $data->name;
         $newUser->email    = $data->email;
         $newUser->password = password_hash($data->password, PASSWORD_DEFAULT);
         $newUser->role     = $role;
         $newUser->is_disabled = 0;

         if (!$this->userModel->save($newUser)) {
             throw new \Exception('Failed to create user', 500);
         }

         return true;
    }
}
