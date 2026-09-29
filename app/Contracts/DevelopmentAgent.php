<?php

namespace App\Contracts;

use App\Models\DevelopmentTask;

interface DevelopmentAgent
{
    public function key(): string;

    public function sandboxFor(DevelopmentTask $task): string;
}
