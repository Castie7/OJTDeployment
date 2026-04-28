<?php

namespace App\Models;

use CodeIgniter\Model;

class AssistantSearchLogModel extends Model
{
    protected $table = 'assistant_search_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';

    protected $allowedFields = [
        'user_id',
        'query',
        'effective_query',
        'mode',
        'result_count',
        'top_research_ids',
        'latency_ms',
        'confidence',
        'is_strong_match',
        'feedback',
        'feedback_note',
        'ip_address',
        'user_agent',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $dateFormat = 'datetime';
}

