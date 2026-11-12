<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MobileEmployeePortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/midtrans/notification', [PaymentController::class, 'notification'])->name('midtrans.notification');
Route::post('/paylabs/notification', [PaymentController::class, 'paylabsNotification'])->name('paylabs.notification');

Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileEmployeePortalController::class, 'login']);

    Route::middleware(['auth:sanctum', 'account.active', 'privilege:employee.portal'])->group(function () {
        Route::get('/employee/portal', [MobileEmployeePortalController::class, 'portal']);
        Route::patch('/employee/profile', [MobileEmployeePortalController::class, 'updateProfile']);
        Route::post('/employee/attendance/clock-in', [MobileEmployeePortalController::class, 'clockIn']);
        Route::patch('/employee/attendance/clock-out', [MobileEmployeePortalController::class, 'clockOut']);
        Route::post('/employee/attendance/manual', [MobileEmployeePortalController::class, 'storeManualAttendance']);
        Route::post('/employee/requests', [MobileEmployeePortalController::class, 'storeRequest']);
        Route::post('/employee/reimbursements', [MobileEmployeePortalController::class, 'storeReimbursement']);
    });
});
