<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\ChannelController as AdminChannelController;
use App\Http\Controllers\Channel\DashboardController as ChannelDashboardController;
use App\Http\Controllers\Channel\ChannelController;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware([\App\Http\Middleware\AdminMiddleware::class])->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/extend', [AdminUserController::class, 'extendSubscription'])->name('users.extend');
        Route::get('/channels', [AdminChannelController::class, 'index'])->name('channels');
        Route::get('/channels/{channel}', [AdminChannelController::class, 'show'])->name('channels.show');
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions');
        Route::post('/subscriptions', [AdminSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::put('/subscriptions/{plan}', [AdminSubscriptionController::class, 'update'])->name('subscriptions.update');
        Route::delete('/subscriptions/{plan}', [AdminSubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
    });

    Route::prefix('channel')->name('channel.')->group(function () {
        Route::get('/dashboard', [ChannelDashboardController::class, 'index'])->name('dashboard');
        Route::get('/channels/create', [ChannelController::class, 'create'])->name('create');
        Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
        Route::get('/channels/{channel}', [ChannelController::class, 'show'])->name('show');
        Route::put('/channels/{channel}', [ChannelController::class, 'update'])->name('update');
        Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->name('destroy');
        Route::post('/channels/{channel}/vod/upload', [ChannelController::class, 'uploadVod'])->name('vod.upload');
        Route::post('/channels/{channel}/vod/youtube', [ChannelController::class, 'addYoutube'])->name('vod.youtube');
        Route::delete('/channels/{channel}/vod/{vodItemId}', [ChannelController::class, 'deleteVod'])->name('vod.delete');
        Route::post('/channels/{channel}/vod/{vodItemId}/refresh-metadata', [ChannelController::class, 'refreshYoutubeMetadata'])->name('vod.refresh');
        Route::post('/channels/{channel}/vod/reorder', [ChannelController::class, 'reorderVod'])->name('vod.reorder');
        Route::put('/channels/{channel}/vod/{vodItemId}', [ChannelController::class, 'updateVodItem'])->name('vod.update');
        Route::post('/channels/{channel}/vod/bulk', [ChannelController::class, 'bulkUpdateVod'])->name('vod.bulk');
        Route::put('/channels/{channel}/playlist/settings', [ChannelController::class, 'updatePlaylistSettings'])->name('playlist.settings');
        Route::get('/channels/{channel}/playlist/stats', [ChannelController::class, 'playlistStats'])->name('playlist.stats');
        Route::put('/channels/{channel}/overlay', [ChannelController::class, 'updateOverlay'])->name('overlay.update');
        Route::post('/channels/{channel}/overlay/logo', [ChannelController::class, 'uploadLogo'])->name('overlay.logo');
    });
});
