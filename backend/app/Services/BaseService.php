<?php

namespace App\Services;

class BaseService
{
    // Common service logic can go here
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }
}
