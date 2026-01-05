<?php

namespace Tests\Feature\Commands;

use App\Enums\MonitoringStatus;
use App\Jobs\CrawlMonitoringSsl;
use App\Jobs\SendSslResult;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SslMonitoringTest extends TestCase
{
    public function test_dispatches_crawl_job_for_active_monitoring()
    {
        Bus::fake();

        Http::fake([
            config('webguard.webguard_core_api_url').'/*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'Test Monitoring',
                    'type' => 'http',
                    'maintenance_active' => false,
                ],
            ]),
        ]);

        $this->artisan('monitoring:ssl')
            ->expectsOutput('Dispatching SSL monitoring jobs...')
            ->expectsOutput('Dispatched SSL monitoring: Test Monitoring')
            ->expectsOutput('SSL monitoring jobs dispatched successfully.')
            ->assertExitCode(0);

        Bus::assertDispatched(CrawlMonitoringSsl::class);
        Bus::assertNotDispatched(SendSslResult::class);
    }

    public function test_dispatches_send_result_job_for_monitoring_in_maintenance()
    {
        Bus::fake();

        Http::fake([
            config('webguard.webguard_core_api_url').'/*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'Test Monitoring',
                    'maintenance_active' => true,
                ],
            ]),
        ]);

        $this->artisan('monitoring:ssl')
            ->expectsOutput('Dispatching SSL monitoring jobs...')
            ->expectsOutput('Skipping SSL monitoring due to active maintenance: Test Monitoring')
            ->expectsOutput('SSL monitoring jobs dispatched successfully.')
            ->assertExitCode(0);

        Bus::assertDispatched(function (SendSslResult $job) {
            return $job->monitoringId === 1 &&
                   $job->status === MonitoringStatus::UNKNOWN &&
                   $job->skippedReason === 'maintenance';
        });
        Bus::assertNotDispatched(CrawlMonitoringSsl::class);
    }

    public function test_handles_no_active_monitorings()
    {
        Bus::fake();

        Http::fake([
            config('webguard.webguard_core_api_url').'/*' => Http::response([]),
        ]);

        $this->artisan('monitoring:ssl')
            ->expectsOutput('Dispatching SSL monitoring jobs...')
            ->expectsOutput('No active SSL monitoring found.')
            ->assertExitCode(0);

        Bus::assertNothingDispatched();
    }
}
