<?php

namespace App\Repositories;

use App\Http\Criterias\CriteriaInterface;

interface RepositoryInterface
{
    public function applyCriteria(CriteriaInterface $criteria);
}
