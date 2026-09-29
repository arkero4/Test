<?php

namespace App\Console\Commands;

use App\Models\IngestionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IngestionToken extends Command
{
    protected $signature = 'orchestrator:ingestion-token {slug} {--revoke : Disable the client and revoke its token}';

    protected $description = 'Issue, rotate, or revoke a requirement ingestion token';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        if (! preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $slug)) {
            $this->error('Use a lowercase client slug (letters, digits, underscore, hyphen).');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $client = IngestionClient::where('slug', $slug)->first();
            if (! $client) {
                $this->error('Client not found.');

                return self::FAILURE;
            }
            $client->update(['status' => 'DISABLED', 'token_hash' => null]);
            $this->info('Ingestion token revoked.');

            return self::SUCCESS;
        }

        $token = 'dvo_ing_'.bin2hex(random_bytes(32));
        IngestionClient::updateOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline($slug), 'token_hash' => hash('sha256', $token), 'status' => 'ACTIVE'],
        );
        $this->warn('Copy this token now; it is shown once. Reissuing invalidates the previous token.');
        $this->line($token);

        return self::SUCCESS;
    }
}
