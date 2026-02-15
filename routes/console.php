<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitoring:response')
    ->everyFiveMinutes()
    ->description('Crawl monitoring responses every fifteen minutes');

Schedule::command('monitoring:ssl')
    ->everyFiveMinutes()
    ->description('Crawl SSL certificates for monitoring every fifteen minutes');
