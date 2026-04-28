<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    
    // ✅ Allowed Fields: Matches your controller logic
    protected $allowedFields    = ['user_id', 'sender_id', 'research_id', 'message', 'is_read', 'created_at'];
    
    protected $returnType       = \App\Entities\Notification::class;
    
    // ⚠️ CRITICAL: Must be FALSE because your table has no 'updated_at' column.
    // The controller manually sets 'created_at', which works perfectly with this.
    protected $useTimestamps    = false; 
}