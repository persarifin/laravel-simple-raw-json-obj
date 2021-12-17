<?php

namespace App\Http\Criterias;

interface CriteriaInterface
{
    public function apply($query);
}
