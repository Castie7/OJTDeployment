<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    
    // ✅ FIX: Added 'role' here. 
    // Without this, the role selected in Vue won't be saved!
    protected $allowedFields = ['name', 'email', 'password', 'role', 'must_change_password', 'is_disabled'];
    protected $returnType    = \App\Entities\User::class;

    // Optional: Use this if your table has 'created_at' and 'updated_at' columns
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
