<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\PexelsController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Topics
Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
Route::get('/topics/create', [TopicController::class, 'create'])->name('topics.create');
Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
Route::get('/topics/{topic}/edit', [TopicController::class, 'edit'])->name('topics.edit');
Route::put('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');
Route::patch('/topics/{topic}/toggle', [TopicController::class, 'toggleActive'])->name('topics.toggle');

// Pexels
Route::get('/pexels', [PexelsController::class, 'index'])->name('pexels.index');
Route::post('/pexels/search', [PexelsController::class, 'search'])->name('pexels.search');
Route::post('/pexels/create-draft', [PexelsController::class, 'createDraft'])->name('pexels.createDraft');

// Queue
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
Route::get('/queue/{post}/edit', [QueueController::class, 'edit'])->name('queue.edit');
Route::put('/queue/{post}', [QueueController::class, 'update'])->name('queue.update');
Route::patch('/queue/{post}/approve', [QueueController::class, 'approve'])->name('queue.approve');
Route::patch('/queue/{post}/unapprove', [QueueController::class, 'unapprove'])->name('queue.unapprove');
Route::post('/queue/{post}/publish-now', [QueueController::class, 'publishNow'])->name('queue.publishNow');
Route::delete('/queue/{post}', [QueueController::class, 'destroy'])->name('queue.destroy');

// Settings
Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
Route::post('/settings/facebook/validate', [SettingController::class, 'validateFacebook'])->name('settings.validateFacebook');
