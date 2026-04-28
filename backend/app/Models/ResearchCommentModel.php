<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchCommentModel extends Model
{
    protected $table            = 'research_comments';
    protected $primaryKey       = 'id';
    protected $returnType       = \App\Entities\ResearchComment::class;
    protected $allowedFields    = ['research_id', 'user_id', 'user_name', 'role', 'comment'];
}