<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Support\Providers;

use AMRCFactoryPlus\Utilities\ServiceClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singletonIf(ServiceClient::class, function (Application $app) {
            return (new ServiceClient())
                ->setBaseUrl(config('manager.base_url'))
                ->setRealm(config('manager.realm'))
                ->setLogger($app->make('log'))
                ->setScheme(config('manager.scheme'))
                ->setCache($app->make('cache.store'))
                ->setPrincipal('sv1manager')
                ->setKeytabPath(config('manager.keytab_path'));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
