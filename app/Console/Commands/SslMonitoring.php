<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MonitoringType;
use App\Jobs\CrawlMonitoringSsl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SslMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:ssl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch monitoring job for SSL certificates.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching SSL monitoring jobs...');

        $location = config('webguard.location');

        $monitoringTypes = collect(MonitoringType::values())
            ->reject(fn (string $type) => $type === MonitoringType::PING->value)
            ->implode(',');

        $response = Http::withHeaders([
            'X-API-KEY' => config('webguard.webguard_core_api_key'),
        ])->get(config('webguard.webguard_core_api_url') . '/api/v1/internal/monitorings', [
            'location' => $location,
            'types' => $monitoringTypes,
        ]);

        if ($response->failed()) {
            $this->error('Failed to fetch monitorings from the Core API.');
            $this->error($response->body());

            return Command::FAILURE;
        }

        $monitorings = $response->json();

        if (empty($monitorings)) {
            $this->info('No active SSL monitoring found.');

            return Command::SUCCESS;
        }

        $this->output->progressStart(count($monitorings));

        foreach ($monitorings as $monitoring) {
            $monitoring = (object) $monitoring;

            if (isset($monitoring->maintenance_active) && $monitoring->maintenance_active) {
                $this->info('Skipping SSL monitoring due to active maintenance: ' . $monitoring->name);
            } else {
                $this->info('Dispatched SSL monitoring: ' . $monitoring->name);

                dispatch(new CrawlMonitoringSsl($monitoring))->onQueue('monitoring-ssl');
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info('SSL monitoring jobs dispatched successfully.');

        return Command::SUCCESS;
    }
}
