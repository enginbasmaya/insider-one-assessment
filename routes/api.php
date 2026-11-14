<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{NotificationController,ObservabilityController};
Route::middleware('throttle:api')->group(function(){
 Route::post('/notifications',[NotificationController::class,'store']);
 Route::post('/notifications/batch',[NotificationController::class,'batch']);
 Route::get('/notifications',[NotificationController::class,'index']);
 Route::get('/notifications/{id}',[NotificationController::class,'show'])->whereUuid('id');
 Route::post('/notifications/{id}/cancel',[NotificationController::class,'cancel'])->whereUuid('id');
 Route::get('/batches/{batchId}',[NotificationController::class,'batchShow'])->whereUuid('batchId');
 Route::get('/health',[ObservabilityController::class,'health']);
 Route::get('/metrics',[ObservabilityController::class,'metrics']);
});
