<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Jobs\CrawlMonitoringSsl;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SslMonitoringTest extends TestCase
{
    public function test_dispatches_crawl_job_for_active_monitoring()
    {
        Bus::fake();

        Http::fake([
            config('webguard.webguard_core_api_url') . '/*' => Http::response([
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
            ->expectsOutput('SSL monitoring dispatch done. total=1 dispatched=1 skipped_maintenance=0')
            ->assertExitCode(0);

        Bus::assertDispatched(CrawlMonitoringSsl::class);
    }

    public function test_skips_job_for_monitoring_in_maintenance()
    {
        Bus::fake();

        Http::fake([
            config('webguard.webguard_core_api_url') . '/*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'Test Monitoring',
                    'maintenance_active' => true,
                ],
            ]),
        ]);

        $this->artisan('monitoring:ssl')
            ->expectsOutput('Dispatching SSL monitoring jobs...')
            ->expectsOutput('SSL monitoring dispatch done. total=1 dispatched=0 skipped_maintenance=1')
            ->assertExitCode(0);

        Bus::assertNotDispatched(CrawlMonitoringSsl::class);
    }

    public function test_handles_no_active_monitorings()
    {
        Bus::fake();

        Http::fake([
            config('webguard.webguard_core_api_url') . '/*' => Http::response([]),
        ]);

        $this->artisan('monitoring:ssl')
            ->expectsOutput('Dispatching SSL monitoring jobs...')
            ->expectsOutput('No active SSL monitoring found.')
            ->assertExitCode(0);

        Bus::assertNothingDispatched();
    }
}
