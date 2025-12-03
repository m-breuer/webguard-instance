<?php

namespace App\Console\Commands;

use App\Jobs\CrawlMonitoringResponse;
use App\Jobs\CrawlMonitoringSsl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CompleteMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch all monitoring jobs for that location.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching all monitoring jobs...');

        $location = config('webguard.location');

        $response = Http::withHeaders([
            'X-API-KEY' => config('webguard.webguard_core_api_key'),
        ])->get(config('webguard.webguard_core_api_url').'/api/v1/internal/monitorings', [
            'location' => $location,
        ]);

        if ($response->failed()) {
            $this->error('Failed to fetch monitorings from the Core API.');
            $this->error($response->body());

            return Command::FAILURE;
        }

        $monitorings = $response->json();

        if (empty($monitorings)) {
            $this->info('No active monitorings found.');

            return Command::SUCCESS;
        }

        $this->output->progressStart(count($monitorings));

        foreach ($monitorings as $monitoring) {
            $monitoring = (object) $monitoring;
            dispatch(new CrawlMonitoringResponse($monitoring))->onQueue('monitoring-response');
            dispatch(new CrawlMonitoringSsl($monitoring))->onQueue('monitoring-ssl');

            $this->info('Dispatched monitoring: '.$monitoring->name);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info('All monitoring jobs have been dispatched successfully.');

        return Command::SUCCESS;
    }
}
