<?php

namespace App\Console\Commands;

use App\Enums\MonitoringStatus;
use App\Enums\MonitoringType;
use App\Jobs\CrawlMonitoringResponse;
use App\Jobs\CrawlMonitoringSsl;
use App\Jobs\SendMonitoringResult;
use App\Jobs\SendSslResult;
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

        $this->call('monitoring:response');
        $this->call('monitoring:ssl');

        $this->info('All monitoring jobs have been dispatched successfully.');

        return Command::SUCCESS;
    }
}
