<?php

namespace App\Contracts;

use App\Models\Requirement;

interface CoordinatorAgent
{
    public function key(): string;

    public function proposePlan(Requirement $requirement): array;
}
