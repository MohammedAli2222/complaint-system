<?php

use App\Events\TestNotification;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/test-reverb', function () {
    try {
        event(new TestNotification('رسالة تجريبية من الباك اند فقط'));
        return response()->json(['status' => 'success', 'message' => 'Notification sent']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    }
});
