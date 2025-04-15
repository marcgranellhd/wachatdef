<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
    ]);
});

// WhatsApp webhook routes (accessible without authentication)
Route::post('/api/webhook/{botId}', [WebhookController::class, 'handleWhatsAppWebhook']);

// Auth routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Superadmin routes
    Route::middleware(['role:superadmin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard');
        Route::resource('tenants', TenantController::class);
        Route::get('/logs', [DashboardController::class, 'logs'])->name('logs');
        Route::get('/stats', [DashboardController::class, 'statistics'])->name('stats');
    });
    
    // Tenant routes
    Route::middleware(['tenant'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'clientDashboard'])->name('dashboard');
        
        // Bot management
        Route::resource('bots', BotController::class);
        Route::post('/bots/{bot}/activate', [BotController::class, 'activate'])->name('bots.activate');
        Route::post('/bots/{bot}/deactivate', [BotController::class, 'deactivate'])->name('bots.deactivate');
        
        // Conversations and messages
        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        
        // Product management
        Route::resource('products', ProductController::class);
        Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');
        Route::post('/products/sync-woocommerce', [ProductController::class, 'syncWooCommerce'])->name('products.sync-woocommerce');
        
        // Analytics
        Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');
    });
});
