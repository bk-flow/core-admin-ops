<?php

namespace App\Core\AdminOps;

use App\Core\System\Support\LoadsModuleMiddleware;
use Illuminate\Support\ServiceProvider;

class AdminOpsServiceProvider extends ServiceProvider
{
    use LoadsModuleMiddleware;

    public function register(): void {}

    public function boot(): void
    {
        $this->loadMiddlewareFrom(__DIR__.'/config/middleware.php');

        if (is_dir(__DIR__.'/resources/lang')) {
            $this->loadTranslationsFrom(__DIR__.'/resources/lang', null);
        }

        if (is_dir(__DIR__.'/resources/views')) {
            $this->loadViewsFrom(__DIR__.'/resources/views', 'core-admin-ops');
        }

        if ($this->app->routesAreCached()) {
            return;
        }

        require __DIR__.'/Routes/admin.php';
    }
}
