<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas de ML Habitat
|--------------------------------------------------------------------------
*/

Schedule::command('uploads:prune')->hourly();
Schedule::command('consultations:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('warranties:expire')->dailyAt('01:00');
Schedule::command('quotes:expire')->dailyAt('00:15');
Schedule::command('visits:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('visits:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('media:prune-orphans')->hourly();
