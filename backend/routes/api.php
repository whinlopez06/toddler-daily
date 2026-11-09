<?php

use App\Http\Controllers\Api\V1\{ChildController, FamilyController, IntakeController, NotificationController, OverviewController, PrivacyController, RecordController};
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    try {
        \Illuminate\Support\Facades\DB::select('SELECT 1');
        return response()->json(['data' => ['status' => 'ready']]);
    } catch (\Throwable) {
        return response()->json(['data' => ['status' => 'unavailable']], 503);
    }
})->middleware('throttle:30,1');

Route::prefix('v1')->middleware(['auth:sanctum', 'demo.access', 'throttle:api'])->group(function () {
    Route::get('me', [FamilyController::class, 'me']);
    Route::middleware(['verified.or.demo', 'active.family'])->group(function () {
        Route::patch('me', [FamilyController::class, 'updateMe']);
        Route::get('family', [FamilyController::class, 'show']);
        Route::patch('family', [FamilyController::class, 'update']);
        Route::post('confirm-password', [FamilyController::class, 'confirmPassword'])->middleware('throttle:5,1');
        Route::get('family/export', [PrivacyController::class, 'export'])->middleware(['recent.password','throttle:exports']);
        Route::delete('family', [PrivacyController::class, 'destroy'])->middleware('recent.password');
        Route::get('dashboard', [OverviewController::class, 'dashboard']);
        Route::get('timeline', [OverviewController::class, 'timeline']);
        Route::get('appointments', [OverviewController::class, 'appointments']);
        Route::get('children', [ChildController::class, 'index']);
        Route::post('children', [ChildController::class, 'store']);
        Route::get('children/{child}', [ChildController::class, 'show']);
        Route::delete('children/{child}', [ChildController::class, 'destroy']);
        Route::patch('children/{child}', [ChildController::class, 'update']);
        Route::get('children/{child}/medications/{medication}/intakes', [IntakeController::class, 'index']);
        Route::post('children/{child}/medications/{medication}/intakes', [IntakeController::class, 'store']);
        Route::get('children/{child}/medications/{medication}/intakes/{intake}', [IntakeController::class, 'show']);
        Route::post('children/{child}/medications/{medication}/intakes/{intake}/corrections', [IntakeController::class, 'correct']);
        Route::get('children/{child}/{recordType}', [RecordController::class, 'index']);
        Route::post('children/{child}/{recordType}', [RecordController::class, 'store']);
        Route::get('children/{child}/{recordType}/{recordId}', [RecordController::class, 'show']);
        Route::patch('children/{child}/{recordType}/{recordId}', [RecordController::class, 'update']);
        Route::delete('children/{child}/{recordType}/{recordId}', [RecordController::class, 'destroy']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/refresh', [NotificationController::class, 'refresh'])->middleware('throttle:12,1');
        Route::patch('notifications/{notification}', [NotificationController::class, 'update']);
    });
});
