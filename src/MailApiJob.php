<?php

namespace Calmadeveloper\MailApi;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class MailApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var array
     */
    private $payload;

    /**
     * @var int
     */
    private const RELEASE_DELAY = 60; //seconds

    /**
     * @var int
     */
    public $tries = 1440;

    /**
     * MailApiJob constructor.
     * @param string $endpoint
     * @param array $payload
     */
    public function __construct(string $endpoint, array $payload)
    {
        $this->endpoint = $endpoint;
        $this->payload = $payload;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function handle()
    {
        try {
            $client = new HttpClient(Arr::add(
                [], 'connect_timeout', 60
            ));

            $response = $client->request('POST', $this->endpoint, $this->payload);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception($response->getBody()->getContents());
            }
        } catch (\Exception $exception) {
            $this->release(self::RELEASE_DELAY);
        }
    }

    public function failed(\Throwable $exception)
    {
        $this->release(self::RELEASE_DELAY);
    }
}
