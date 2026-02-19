<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MonitoringStatus;
use App\Jobs\CrawlMonitoringResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResponseMonitoringTest extends TestCase
{
    public function test_dispatches_crawl_job_for_active_monitoring()
    {
        Bus::fake();

        config(['webguard.webguard_core_api_url' => 'http://api.test']);

        Http::fake([
            config('webguard.webguard_core_api_url') . '/api/v1/internal/monitorings*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'Test Monitoring',
                    'type' => 'http',
                    'maintenance_active' => false,
                ],
            ]),
        ]);

        $this->artisan('monitoring:response')
            ->expectsOutput('Dispatching response monitoring jobs...')
            ->expectsOutput('Response monitoring dispatch done. total=1 dispatched=1 skipped_maintenance=0')
            ->assertExitCode(0);

        Bus::assertDispatched(CrawlMonitoringResponse::class);
    }

    public function test_dispatches_send_result_job_for_monitoring_in_maintenance()
    {
        Bus::fake();

        config(['webguard.webguard_core_api_url' => 'http://api.test']);

        Http::fake([
            config('webguard.webguard_core_api_url') . '/api/v1/internal/monitorings*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'Test Monitoring',
                    'maintenance_active' => true,
                ],
            ]),
            config('webguard.webguard_core_api_url') . '/api/v1/internal/monitoring-responses' => Http::response(),
        ]);

        $this->artisan('monitoring:response')
            ->expectsOutput('Dispatching response monitoring jobs...')
            ->expectsOutput('Response monitoring dispatch done. total=1 dispatched=0 skipped_maintenance=1')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return $request->url() === config('webguard.webguard_core_api_url') . '/api/v1/internal/monitoring-responses' &&
                   $request['monitoring_id'] === 1 &&
                   $request['status'] === MonitoringStatus::UNKNOWN;
        });

        Bus::assertNotDispatched(CrawlMonitoringResponse::class);
    }

    public function test_handles_no_active_monitorings()
    {
        Bus::fake();

        config(['webguard.webguard_core_api_url' => 'http://api.test']);

        Http::fake([
            config('webguard.webguard_core_api_url') . '/api/v1/internal/monitorings*' => Http::response([]),
        ]);

        $this->artisan('monitoring:response')
            ->expectsOutput('Dispatching response monitoring jobs...')
            ->expectsOutput('No active response monitoring found.')
            ->assertExitCode(0);

        Bus::assertNothingDispatched();
    }
}
