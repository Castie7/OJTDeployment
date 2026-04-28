<?php

if (!function_exists('log_activity')) {
    function log_activity($userId, $userName, $userRole, $action, $details)
    {
        $activityLogModel = new \App\Models\ActivityLogModel();
        
        $data = [
            'user_id'   => $userId,
            'user_name' => $userName,
            'role'      => $userRole, // Fixed: 'user_role' -> 'role'
            'action'    => $action,
            'details'   => $details,
            'ip_address' => service('request')->getIPAddress(),
            // 'created_at' is handled by $useTimestamps in Model
        ];

        try {
            $activityLogModel->insert($data);
        } catch (\Exception $e) {
            log_message('error', '[Activity Log] Failed to insert log: ' . $e->getMessage());
        }
    }
}
