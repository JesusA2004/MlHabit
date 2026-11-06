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
Schedule::command('consultations:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('visits:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('media:prune-orphans')->hourly();
// Respaldo local: lo que se guardó en el servidor porque el NAS no respondía
// se sube solo en cuanto vuelve (ver docs/ALMACENAMIENTO-SYNOLOGY.md).
Schedule::command('media:sync-pending')->everyTenMinutes()->withoutOverlapping(60);
