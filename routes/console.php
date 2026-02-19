<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitoring:response')->everyFiveMinutes();

Schedule::command('monitoring:ssl')->everyFiveMinutes();
