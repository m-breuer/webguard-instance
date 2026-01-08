<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompleteMonitoringTest extends TestCase
{
    public function test_calls_response_and_ssl_monitoring_commands()
    {
        Http::fake([
            config('webguard.webguard_core_api_url') . '/*' => Http::response([]),
        ]);

        $this->artisan('monitoring:complete')
            ->expectsOutput('Dispatching all monitoring jobs...')
            ->expectsOutputToContain('Dispatching response monitoring jobs...')
            ->expectsOutputToContain('No active response monitoring found.')
            ->expectsOutputToContain('Dispatching SSL monitoring jobs...')
            ->expectsOutputToContain('No active SSL monitoring found.')
            ->expectsOutput('All monitoring jobs have been dispatched successfully.')
            ->assertExitCode(0);
    }
}
