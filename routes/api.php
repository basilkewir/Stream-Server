<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChannelController as ApiChannelController;
use App\Http\Controllers\Api\AdminController as ApiAdminController;
use App\Http\Controllers\Api\StreamAuthController;

// Public - called by media server on_publish/on_unpublish hooks
Route::post('/stream/authorize', [StreamAuthController::class, 'authorizeStream']);
Route::post('/stream/unauthorize', [StreamAuthController::class, 'unauthorizeStream']);
Route::get('/stream/flussonic/status', [StreamAuthController::class, 'flussonicStatus']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/channels/{channel}/status', [ApiChannelController::class, 'status']);
    Route::get('/channels/{channel}/ingest', [ApiChannelController::class, 'ingestInfo']);
    Route::post('/channels/{channel}/vod/upload', [ApiChannelController::class, 'uploadVod']);
    Route::post('/channels/{channel}/vod/youtube', [ApiChannelController::class, 'addYoutube']);
    Route::delete('/channels/{channel}/vod/{vodItemId}', [ApiChannelController::class, 'deleteVod']);
    Route::post('/channels/{channel}/vod/reorder', [ApiChannelController::class, 'reorderVod']);
    Route::put('/channels/{channel}/overlay', [ApiChannelController::class, 'updateOverlay']);
    Route::get('/channels/{channel}/overlay', [ApiChannelController::class, 'getOverlay']);
    Route::post('/channels/{channel}/overlay/logo', [ApiChannelController::class, 'uploadLogo']);

    Route::get('/admin/subscriptions', [ApiAdminController::class, 'subscriptions']);
    Route::post('/admin/subscriptions', [ApiAdminController::class, 'createSubscription']);
    Route::put('/admin/subscriptions/{plan}', [ApiAdminController::class, 'updateSubscription']);
    Route::delete('/admin/subscriptions/{plan}', [ApiAdminController::class, 'deleteSubscription']);
    Route::get('/admin/users', [ApiAdminController::class, 'users']);
    Route::put('/admin/users/{user}', [ApiAdminController::class, 'updateUser']);
    Route::get('/admin/stats', [ApiAdminController::class, 'stats']);
});
