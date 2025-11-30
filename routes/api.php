<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Termwind\Components\Raw;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [UserController::class, 'register'])->middleware('throttle:5,1');
Route::post('/verify-otp', [UserController::class, 'verifyOtp'])->middleware('throttle:5,1');
Route::post('/login', [UserController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/logout', [UserController::class, 'logout']);
    //Get Citizen Info By ID
    Route::get('/Citizen/{id}', [UserController::class, 'getCitizen']);
    //Get All Users(Citizens+Employees)
    Route::get('/users', [UserController::class, 'index']);
});



Route::middleware(['auth:sanctum'])->group(function () {

    // مسارات خاصة بالمواطن فقط
    Route::middleware(['role:citizen'])->group(function () {
        Route::post('/complaints', [ComplaintController::class, 'store']); // تقديم شكوى
        Route::get('/complaints/{ref}', [ComplaintController::class, 'show']);
        Route::get('/complaints/{ref}/track', [ComplaintController::class, 'track']);
        Route::post('/complaints/{id}/respondToInfoRequest', [ComplaintController::class, 'respondToInfoRequest']);
    });

    Route::get('/complaints/{id}/lock', [ComplaintController::class, 'lock']);
    Route::put('/complaints/{id}/status', [ComplaintController::class, 'updateStatus']);
    Route::post('/complaints/{id}/assign', [ComplaintController::class, 'assign']);
    Route::post('/complaints/{id}/notes', [ComplaintController::class, 'addNote']);
    Route::post('/complaints/{id}/request-info', [ComplaintController::class, 'requestMoreInfo']);
    Route::get('/complaints/{id}/getInfoRequestMessage', [ComplaintController::class, 'getInfoRequestMessage']);


    // مسارات خاصة بالموظف
    Route::middleware(['role:employee'])->group(function () {
        Route::post('/complaints/{id}/unlock', [ComplaintController::class, 'unlock']);
    });

    // مسارات خاصة بالمشرف
    Route::prefix('employees')->group(function () {
        // store
        Route::post('/', [AdminUserController::class, 'store']);
        // update
        Route::put('/{id}', [AdminUserController::class, 'update']);
        // updatePermissions
        Route::put('/{id}/permissions', [AdminUserController::class, 'updatePermissions']);
    });
    //Get All Complaints
    Route::post('/getAllComplaints', [ComplaintController::class, 'getAllComplaints']);
    // destroy
    Route::delete('user/{id}', [AdminUserController::class, 'destroy']);
    //Get System Performance

    Route::post('/getEmployeeNewComplaints', [ComplaintController::class, 'getEmployeeNewComplaints']);
});
