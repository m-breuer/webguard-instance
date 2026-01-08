<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
