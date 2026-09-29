<?php

namespace App\Http\Middleware;

use App\Models\Worker;
use Closure;
use Illuminate\Http\Request;

class AuthenticateWorker
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! $token || strlen($token) < 32) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $worker = Worker::where('token_hash', hash('sha256', $token))->first();
        if (! $worker || $worker->status === 'DISABLED') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $request->attributes->set('worker', $worker);

        return $next($request);
    }
}
