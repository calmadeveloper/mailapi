<?php

namespace Calmadeveloper\MailApi;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class MailApiProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/mailapi.php' => config_path('mailapi.php')
        ]);

        $this->registerSwiftTransport();
    }

    public function registerSwiftTransport()
    {
        Mail::extend('mailapi', function () {
            $config = $this->app['config']->get('mailapi', []);

            return new MailApiTransport($config);
        });
    }
}
