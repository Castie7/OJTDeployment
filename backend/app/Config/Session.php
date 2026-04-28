<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Session\Handlers\DatabaseHandler; // 1. Import This

class Session extends BaseConfig
{
    // 2. Change Driver to Database
    public string $driver = DatabaseHandler::class;

    public string $cookieName = 'ci_session';
    public int $expiration = 7200;

    // 3. Point to your new table
    public string $savePath = 'ci_sessions'; 

    public bool $matchIP = false;
    public int $timeToUpdate = 300;
    public bool $regenerateDestroy = false;
    public ?string $group = null;
}