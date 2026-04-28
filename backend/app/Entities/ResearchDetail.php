<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ResearchDetail extends Entity
{
    protected $datamap = [];
    protected $dates   = ['publication_date'];
    protected $casts   = [];
}
