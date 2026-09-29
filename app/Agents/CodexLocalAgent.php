<?php

namespace App\Agents;

use App\Contracts\DevelopmentAgent;
use App\Models\DevelopmentTask;

class CodexLocalAgent implements DevelopmentAgent
{
    public function key(): string
    {
        return 'codex_local';
    }

    public function sandboxFor(DevelopmentTask $task): string
    {
        return $task->type === 'technical_analysis' ? 'read-only' : 'workspace-write';
    }
}
