<?php

namespace App\Http\Middleware;

use App\Models\IngestionClient;
use Closure;
use Illuminate\Http\Request;

class AuthenticateIngestionClient
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! is_string($token) || ! preg_match('/\Advo_ing_[a-f0-9]{64}\z/', $token)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $client = IngestionClient::where('token_hash', hash('sha256', $token))->where('status', 'ACTIVE')->first();
        if (! $client) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $client->update(['last_used_at' => now()]);
        $request->attributes->set('ingestionClient', $client);

        return $next($request);
    }
}
