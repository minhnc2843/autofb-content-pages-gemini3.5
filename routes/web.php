<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\PexelsController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\PageInsightController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\AICaptionController;
use App\Http\Controllers\StrategyController;
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
Route::post('/pexels/analyze-media', [PexelsController::class, 'analyzeMedia'])->name('pexels.analyzeMedia');

// Queue
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
Route::post('/queue/batch', [QueueController::class, 'batchAction'])->name('queue.batch');
Route::post('/queue/publish-due-now', [QueueController::class, 'publishDueNow'])->name('queue.publishDueNow');
Route::get('/queue/{post}/edit', [QueueController::class, 'edit'])->name('queue.edit');
Route::put('/queue/{post}', [QueueController::class, 'update'])->name('queue.update');
Route::patch('/queue/{post}/approve', [QueueController::class, 'approve'])->name('queue.approve');
Route::patch('/queue/{post}/unapprove', [QueueController::class, 'unapprove'])->name('queue.unapprove');
Route::post('/queue/{post}/publish-now', [QueueController::class, 'publishNow'])->name('queue.publishNow');
Route::post('/queue/{post}/analyze', [QueueController::class, 'analyze'])->name('queue.analyze');
Route::delete('/queue/{post}', [QueueController::class, 'destroy'])->name('queue.destroy');

// Calendar
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::post('/calendar/generate', [CalendarController::class, 'generate'])->name('calendar.generate');

// Settings
Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
Route::post('/settings/facebook/validate', [SettingController::class, 'validateFacebook'])->name('settings.validateFacebook');

// Insights & Audits
Route::post('/insights/sync', [PageInsightController::class, 'sync'])->name('insights.sync');
Route::post('/insights/audit', [PageInsightController::class, 'audit'])->name('insights.audit');
Route::post('/ai/generate-captions', [AICaptionController::class, 'generate'])->name('ai.generate-captions');

// Strategy Engine
Route::get('/strategy', [StrategyController::class, 'index'])->name('strategy.index');
Route::post('/strategy/generate', [StrategyController::class, 'generate'])->name('strategy.generate');
