<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchIndexJobModel extends Model
{
    protected $table = 'research_index_jobs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';

    protected $allowedFields = [
        'research_id',
        'status',
        'reason',
        'attempt_count',
        'max_attempts',
        'priority',
        'last_error',
        'next_retry_at',
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $dateFormat = 'datetime';
}

