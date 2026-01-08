<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitoring:response')
    ->everyMinute()
    ->description('Crawl monitoring responses every minute');

Schedule::command('monitoring:ssl')
    ->everyMinute()
    ->description('Crawl SSL certificates for monitoring every minute');
