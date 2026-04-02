<?php

use App\Core\AdminOps\Http\Controllers\Admin\AdminDashboardController;
use App\Providers\AdminCmsRouteStack;
use Illuminate\Support\Facades\Route;

AdminCmsRouteStack::withLocale(function (): void {
    AdminCmsRouteStack::withAdminAuth(function (): void {
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])
                ->name('cms.admin.dashboard')
                ->middleware('cms_permission:dashboard_read');
        });
    });
});
