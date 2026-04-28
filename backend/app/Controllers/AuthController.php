<?php

namespace App\Controllers;

use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    protected $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
        helper(['activity']); // Load Logging Helper
    }

    // ------------------------------------------------------------------
    // 1. LOGIN
    // ------------------------------------------------------------------
    public function login()
    {
        try {
            $ip = $this->request->getIPAddress();
            
            // --- RATE LIMITING (THROTtLER) ---
            $throttler = \Config\Services::throttler();
            $maxAttempts = 5;
            $lockoutSecs = 60; // 1 minute lockout
            
            // Check if this IP is currently blocked
            if ($throttler->check('login_attempts_' . md5($ip), $maxAttempts, $lockoutSecs) === false) {
                // Determine how many seconds remain before they can try again
                $retryAfter = $throttler->getTokenTime(); // Usually returns time of the bucket replenish
                // Calculate simple remaining time for error message (rough estimate based on config)
                // For a more precise wait time, getTokenTime can be used if configured specifically, 
                 return $this->response
                    ->setStatusCode(429)
                    ->setJSON([
                    'status' => 'error',
                    'message' => "Too many failed attempts. Please try again later.",
                    'retry_after' => $lockoutSecs
                ]);
            }

            $json = $this->request->getJSON();
            if (!$json || !is_object($json)) {
                return $this->fail('Invalid JSON payload', 400);
            }

            $email = isset($json->email) && is_string($json->email) ? trim($json->email) : '';
            $password = isset($json->password) && is_string($json->password) ? $json->password : '';

            // 🛑 SENIOR FIX: Security Validation & Bcrypt DoS Protection
            if (empty($email) || empty($password) || strlen($password) > 72 || strlen($email) > 255) {
                return $this->failUnauthorized("Invalid email or password.");
            }

            $loginResult = $this->authService->login($email, $password);
            $user = $loginResult['user'] ?? null;

            if (($loginResult['status'] ?? null) === 'success' && $user) {
                // Success: reset throttler intentionally if possible, though CI4 throttler 
                // doesn't have a direct 'clear' method. Valid logins will just pass.
                // We could let the token bucket decay naturally.

                // LOG ACTIVITY
                log_activity($user->id, $user->name, $user->role, 'LOGIN', "User logged in via email: $email");

                return $this->respond([
                    'status' => 'success',
                    'message' => 'Login Successful!',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role
                    ],
                    'must_change_password' => (bool) ($user->must_change_password ?? false)
                ]);
            }

            if (($loginResult['status'] ?? null) === 'disabled') {
                return $this->response
                    ->setStatusCode(423)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'This account is disabled. Please contact the administrator.',
                    ]);
            }

            // Failure: the throttler check() above already registered a hit.
            // We just need to notify the user.
            return $this->failUnauthorized("Invalid email or password.");
        }
        catch (\Throwable $e) {
            log_message('critical', '[Login Error] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->failServerError("An internal server error occurred. Please try again later.");
        }
    }

    // ------------------------------------------------------------------
    // 2. VERIFY SESSION
    // ------------------------------------------------------------------
    public function verify()
    {
        // 🔒 Call this explicitly to force CodeIgniter to generate the secure
        // CSRF Token and attach it to the browser's Set-Cookie header automatically.
        csrf_hash();

        $sessionData = $this->authService->verifySession();

        if (!$sessionData) {
            return $this->response->setJSON([
                'status' => 'guest',
                'message' => 'User is not logged in'
            ]);
        }

        // Check if user must change their password (survives page reloads)
        $userModel = new \App\Models\UserModel();
        $dbUser = $userModel->find($sessionData['id']);
        $mustChange = $dbUser ? (bool) ($dbUser->must_change_password ?? false) : false;

        return $this->response->setJSON([
            'status' => 'success',
            'user' => $sessionData,
            'must_change_password' => $mustChange
        ]);
    }

    // ------------------------------------------------------------------
    // 3. LOGOUT
    // ------------------------------------------------------------------
    public function logout()
    {
        // Get user before destroying session
        $userId = session()->get('id');
        $userName = session()->get('name');
        $role = session()->get('role');

        if ($userId) {
            log_activity($userId, $userName, $role, 'LOGOUT', 'User logged out');
        }

        $this->authService->logout();
        return $this->respond(['status' => 'success', 'message' => 'Logged out successfully']);
    }

    // ------------------------------------------------------------------
    // 4. UPDATE PROFILE
    // ------------------------------------------------------------------
    public function updateProfile()
    {
        $json = $this->request->getJSON();
        if (!$json || !is_object($json) || !isset($json->user_id) || !is_numeric($json->user_id)) {
            return $this->failUnauthorized('Invalid request');
        }

        try {
            $currentUserId = session()->get('id');
            $currentUserRole = session()->get('role');

            // 🛑 SENIOR FIX: Strong Type Constraints to prevent Type Confusion
            $safeData = new \stdClass();
            if (isset($json->name) && is_string($json->name)) $safeData->name = trim($json->name);
            if (isset($json->email) && is_string($json->email)) $safeData->email = trim($json->email);
            if (isset($json->current_password) && is_string($json->current_password)) $safeData->current_password = $json->current_password;
            if (isset($json->new_password) && is_string($json->new_password)) $safeData->new_password = $json->new_password;

            // Bcrypt DoS Protection
            if ((isset($safeData->new_password) && strlen($safeData->new_password) > 72) || 
                (isset($safeData->current_password) && strlen($safeData->current_password) > 72)) {
                return $this->fail('Password too long', 400);
            }

            $updatedUser = $this->authService->updateProfile((int)$json->user_id, $safeData, $currentUserId, $currentUserRole);

            // LOG ACTIVITY
            log_activity($currentUserId, session()->get('name'), $currentUserRole, 'UPDATE_PROFILE', "Updated profile for user ID: {$json->user_id}");

            return $this->respond([
                'status' => 'success',
                'message' => 'Account updated successfully',
                'user' => [
                    'id' => $updatedUser->id,
                    'name' => $updatedUser->name,
                    'email' => $updatedUser->email,
                    'role' => $updatedUser->role,
                ]
            ]);

        }
        catch (\Throwable $e) {
            log_message('error', '[Update Profile Error] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            // 🛑 SENIOR FIX: Never leak raw System Errors or TypeErrors
            if ($e instanceof \Error) {
                return $this->failServerError('An internal server error occurred.');
            }
            return $this->fail($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // ------------------------------------------------------------------
    // 5. REGISTER
    // ------------------------------------------------------------------
    public function register()
    {
        if (!session()->get('isLoggedIn') || session()->get('role') !== 'admin') {
            return $this->failForbidden('Access Denied: Admins only.');
        }

        $json = $this->request->getJSON();

        if (!$json || !is_object($json)) {
            return $this->fail('Invalid JSON payload', 400);
        }

        try {
            $safeData = new \stdClass();
            $safeData->name = isset($json->name) && is_string($json->name) ? trim($json->name) : '';
            $safeData->email = isset($json->email) && is_string($json->email) ? trim($json->email) : '';
            $safeData->password = isset($json->password) && is_string($json->password) ? $json->password : '';
            
            if (empty($safeData->name) || empty($safeData->email) || empty($safeData->password)) {
                return $this->fail('Missing required fields', 400);
            }
            if (strlen($safeData->password) > 72 || strlen($safeData->email) > 255) {
                return $this->fail('Invalid payload length', 400);
            }

            $safeData->role = 'user';
            if (isset($json->role) && is_string($json->role) && in_array($json->role, ['user', 'admin'], true)) {
                $safeData->role = $json->role;
            }

            $this->authService->register($safeData);

            $adminId = session()->get('id');
            $adminName = session()->get('name');
            $adminRole = session()->get('role');

            log_activity($adminId, $adminName, $adminRole, 'REGISTER_USER', "Registered new user: " . ($json->email ?? 'unknown'));

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'User added successfully'
            ]);
        }
        catch (\Throwable $e) {
            log_message('error', '[Register Error] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            if ($e instanceof \Error) {
                return $this->failServerError('An internal server error occurred.');
            }

            // Handle specific codes if needed
            if ($e->getCode() == 409) {
                return $this->failResourceExists($e->getMessage());
            }
            return $this->failServerError('An internal server error occurred.');
        }
    }
}
