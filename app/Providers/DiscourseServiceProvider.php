<?php

namespace App\Providers;

use App\Services\DiscourseService;
use Illuminate\Support\ServiceProvider;

class DiscourseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(DiscourseService::class, function () {
            return new DiscourseService(config('services.discourse'));
        });
    }
}
