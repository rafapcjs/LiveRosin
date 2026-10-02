<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas automáticas
|--------------------------------------------------------------------------
| En el servidor se activan con una sola línea en el cron:
|   * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
*/

// Borra los registros de seguridad de más de 90 días.
Schedule::command('model:prune', ['--model' => [App\Models\SecurityEvent::class]])->dailyAt('03:30');
