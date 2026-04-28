<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Research extends Entity
{
    protected $datamap = [];
    protected $dates   = [
        'created_at', 
        'updated_at', 
        'deleted_at',
        'approved_at',
        'rejected_at',
        'archived_at',
        'publication_date',
        'deadline_date'
    ];
    protected $casts   = [];
}
