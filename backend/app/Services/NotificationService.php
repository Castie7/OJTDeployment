<?php

namespace App\Services;

use App\Models\NotificationModel;

class NotificationService extends BaseService
{
    protected $notifModel;

    public function __construct()
    {
        parent::__construct();
        $this->notifModel = new NotificationModel();
    }

    public function getUserNotifications($userId)
    {
        if (!$this->db->tableExists('notifications')) {
            return [];
        }

        return $this->notifModel->where('user_id', $userId)
                      ->orderBy('created_at', 'DESC')
                      ->findAll(10);
    }

    public function markAsRead($userId)
    {
        if (!$this->db->tableExists('notifications')) {
            return true;
        }

        $this->notifModel->where('user_id', $userId)
              ->where('is_read', 0)
              ->set(['is_read' => 1])
              ->update();
        
        return true;
    }
}
