<?php

namespace App\Providers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class GoogleClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\Google_Client::class, function () {
            $client = new \Google_Client();
            $client->setApplicationName("My Application");
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.redirect_uri'));
            $client->setAccessType('offline');
            $client->addScope( \Google\Service\Drive::DRIVE_FILE );

            if(Session::has('access_token')) {
                $client->setAccessToken(Session::get('access_token'));
            }

            return $client;
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
