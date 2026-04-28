<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class AdminController extends BaseController
{
    use ResponseTrait;

    public function __construct()
    {
        helper('activity');
    }

    private function invalidateUserSessions(int $targetUserId, ?string $skipSessionId = null): void
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('ci_sessions')) {
            return;
        }

        $sessions = $db->table('ci_sessions')->get()->getResultArray();
        foreach ($sessions as $sess) {
            if ($skipSessionId !== null && $sess['id'] === $skipSessionId) {
                continue;
            }

            $data = $sess['data'] ?? '';
            if (str_contains($data, "id|i:{$targetUserId};") ||
                str_contains($data, "\"id\";i:{$targetUserId};")) {
                $db->table('ci_sessions')->where('id', $sess['id'])->delete();
            }
        }
    }

    private function validatePasswordStrength(string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'Password must be at least 10 characters long.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must include at least one lowercase letter.';
        }

        if (!preg_match('/\d/', $password)) {
            return 'Password must include at least one number.';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return 'Password must include at least one special character.';
        }

        return null;
    }

    // GET /admin/users
    public function index()
    {
        // 🔒 SECURITY CHECK: Strict Admin Only
        // We check the session because we are using cookies
        if (session()->get('role') !== 'admin') {
             return $this->failForbidden('Access Denied: Admins only.');
        }

        // ❌ REMOVED: Manual CORS headers
        // The Global App\Filters\Cors handles this safely now.

        // 2. Fetch Users
        $userModel = new UserModel();
        
        // Select specific fields (security best practice: don't send passwords)
        $users = $userModel->select('id, name, email, role, created_at, is_disabled')
                           ->orderBy('created_at', 'DESC')
                           ->findAll();

        return $this->respond($users);
    }

    // POST/PATCH /admin/users/{id}/status
    public function updateStatus($userId = null)
    {
        if (session()->get('role') !== 'admin') {
             return $this->failForbidden('Access Denied');
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            return $this->fail('Invalid user ID', 400);
        }

        $json = $this->request->getJSON();
        if (!$json || !property_exists($json, 'is_disabled')) {
            return $this->fail('Missing required fields', 400);
        }

        $rawStatus = $json->is_disabled;
        $isDisabled = filter_var($rawStatus, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($isDisabled === null && !in_array($rawStatus, [0, 1, '0', '1'], true)) {
            return $this->fail('Invalid account status value', 400);
        }

        $isDisabled = (bool) $isDisabled;
        $currentUserId = (int) session()->get('id');

        if ($isDisabled && $currentUserId === $userId) {
            return $this->fail('You cannot disable your own account while signed in.', 422);
        }

        $userModel = new UserModel();
        $targetUser = $userModel->find($userId);
        if (!$targetUser) {
            return $this->failNotFound('User not found');
        }

        if ($isDisabled && $targetUser->role === 'admin') {
            $remainingEnabledAdmins = $userModel
                ->where('role', 'admin')
                ->where('is_disabled', 0)
                ->where('id !=', $userId)
                ->countAllResults();

            if ($remainingEnabledAdmins < 1) {
                return $this->fail('At least one enabled administrator account must remain.', 422);
            }
        }

        if (!$userModel->update($userId, [
            'is_disabled' => $isDisabled ? 1 : 0,
        ])) {
            return $this->fail('Failed to update account status', 500);
        }

        if ($isDisabled) {
            $this->invalidateUserSessions($userId, session_id());
        }

        $adminId = (int) session()->get('id');
        $adminName = (string) session()->get('name');
        $adminRole = (string) session()->get('role');
        $action = $isDisabled ? 'DISABLE_USER' : 'ENABLE_USER';
        $detail = ($isDisabled ? 'Disabled' : 'Enabled') . " user account: {$targetUser->email}";
        log_activity($adminId, $adminName, $adminRole, $action, $detail);

        return $this->respond([
            'status' => 'success',
            'message' => $isDisabled ? 'Account disabled successfully.' : 'Account enabled successfully.',
        ]);
    }
    
    // POST /admin/reset-password
    public function resetPassword()
    {
        // 🔒 SECURITY CHECK
        if (session()->get('role') !== 'admin') {
             return $this->failForbidden('Access Denied');
        }

        // ❌ REMOVED: Manual CORS headers

        $json = $this->request->getJSON();

        if (!$json || !isset($json->user_id) || !isset($json->new_password)) {
            return $this->fail('Missing required fields', 400);
        }

        $newPassword = trim((string) $json->new_password);
        $passwordError = $this->validatePasswordStrength($newPassword);
        if ($passwordError !== null) {
            return $this->fail($passwordError, 422);
        }

        $userModel = new UserModel();
        
        // Verify user exists first
        if (!$userModel->find($json->user_id)) {
            return $this->failNotFound('User not found');
        }

        $userModel->update($json->user_id, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 1, // Force user to set their own password on next login
        ]);

        // 🔒 FORCE IMMEDIATE LOGOUT: Destroy all active sessions for the target user.
        // The ci_sessions table stores serialized PHP data. We search for 'id|'
        // followed by the user ID pattern in the session data blob.
        $targetUserId = (int) $json->user_id;
        $currentSessionId = session_id();
        $this->invalidateUserSessions($targetUserId, $currentSessionId);

        return $this->respond(['status' => 'success', 'message' => 'Password reset successful. User has been logged out.']);
    }
}
