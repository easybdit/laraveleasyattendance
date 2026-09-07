<?php

/*
|--------------------------------------------------------------------------
| ZKTeco ADMS (Cloud Server) push routes — public, unauthenticated
|--------------------------------------------------------------------------
|
| Point a device's "Cloud Server Setting" -> Server Address at this app's
| domain (Server Mode: ADMS). The device firmware calls these exact,
| un-prefixed paths itself — do not rename or move them.
|
| Registered by LaravelEasyAttendanceServiceProvider WITHOUT the 'web'
| middleware group, so there's no session/CSRF handling in the way (a
| device can't carry either) — nothing to configure in bootstrap/app.php
| for this to work, unlike a plain routes/web.php registration would need.
|
*/

use Easybdit\LaravelEasyAttendance\Http\Controllers\AdmsPushController;
use Illuminate\Support\Facades\Route;

Route::get('/iclock/cdata', [AdmsPushController::class, 'handshake']);
Route::post('/iclock/cdata', [AdmsPushController::class, 'store']);
Route::get('/iclock/getrequest', [AdmsPushController::class, 'pendingCommands']);
Route::post('/iclock/devicecmd', [AdmsPushController::class, 'commandAck']);
