<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Support\Providers;

use AMRCFactoryPlus\ServiceClient;
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
        $ccache = new \KRB5CCache;

        $this->app->singletonIf(ServiceClient::class, function (Application $app) use ($ccache) {

            if (auth()->user()) {
                return (new ServiceClient())
                    ->setBaseUrl(config('manager.base_url'))
                    ->setRealm(config('manager.realm'))
                    ->setLogger($app->make('log'))
                    ->setScheme(config('manager.scheme'))
                    ->setCache($app->make('cache.store'))
                    ->setCcache($ccache->open("FILE:/app/storage/".auth()->user()->username . '.ccache'));
            }

            return (new ServiceClient())
                ->setBaseUrl(config('manager.base_url'))
                ->setRealm(config('manager.realm'))
                ->setLogger($app->make('log'))
                ->setScheme(config('manager.scheme'))
                ->setCache($app->make('cache.store'))
                ->setPrincipal(config('manager.manager_client_principal'))
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
