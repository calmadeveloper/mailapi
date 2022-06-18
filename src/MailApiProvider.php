<?php

namespace Calmadeveloper\MailApi;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Arr;
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
        $this->app['swift.transport']->extend('mailapi', function($app) {
            $config = $this->app['config']->get('mailapi', []);
            $guzzle = new HttpClient(Arr::add(
                $config['guzzle'] ?? [], 'connect_timeout', 60
            ));

            return new MailApiTransport(
                $guzzle,
                $config['api_key'],
                $config['endpoint']
            );
        });
    }
}
