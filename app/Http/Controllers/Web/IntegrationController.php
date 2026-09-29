<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IngestionClient;
use App\Models\RequirementEvent;

class IntegrationController extends Controller
{
    public function __invoke()
    {
        $clients = IngestionClient::orderBy('name')->get()->map(function (IngestionClient $client) {
            $client->requirements_received = RequirementEvent::where('actor', 'integration:'.$client->slug)
                ->where('action', 'requirement.created')->count();

            return $client;
        });

        return view('integrations.index', ['clients' => $clients]);
    }
}
