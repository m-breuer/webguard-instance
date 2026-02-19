<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MonitoringStatus;
use App\Jobs\CrawlMonitoringResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ResponseMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:response';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch monitoring job for a response.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching response monitoring jobs...');

        $location = config('webguard.location');

        $response = Http::withHeaders([
            'X-API-KEY' => config('webguard.webguard_core_api_key'),
        ])->get(config('webguard.webguard_core_api_url') . '/api/v1/internal/monitorings', [
            'location' => $location,
        ]);

        if ($response->failed()) {
            $this->error('Failed to fetch monitorings from the Core API.');
            $this->error($response->body());

            return Command::FAILURE;
        }

        $monitorings = $response->json();

        if (empty($monitorings)) {
            $this->info('No active response monitoring found.');

            return Command::SUCCESS;
        }

        $dispatched = 0;
        $skippedMaintenance = 0;

        foreach ($monitorings as $monitoring) {
            $monitoring = (object) $monitoring;

            if (isset($monitoring->maintenance_active) && $monitoring->maintenance_active) {
                Http::withHeaders([
                    'X-API-KEY' => config('webguard.webguard_core_api_key'),
                ])->post(config('webguard.webguard_core_api_url') . '/api/v1/internal/monitoring-responses', [
                    'monitoring_id' => $monitoring->id,
                    'status' => MonitoringStatus::UNKNOWN,
                ]);

                $skippedMaintenance++;
            } else {
                dispatch(new CrawlMonitoringResponse($monitoring))->onQueue('monitoring-response');

                $dispatched++;
            }
        }

        $this->info(sprintf(
            'Response monitoring dispatch done. total=%d dispatched=%d skipped_maintenance=%d',
            count($monitorings),
            $dispatched,
            $skippedMaintenance,
        ));

        return Command::SUCCESS;
    }
}
