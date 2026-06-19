<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GeneralService
{
    public static function slackNotification(string $webhook, string $message, ?string $channel = ''): ?int
    {
        try {
            if (config('app.env') === 'production') {
                $client = new Client;
                $response = $client->request('POST', $webhook, [
                    'json' => [
                        'channel' => $channel,
                        'username' => env('APP_NAME', 'Price Movement Alerts'),
                        'text' => trim($message),
                    ],
                ]);

                return $response->getStatusCode();
            }

            return null;
        } catch (\Exception $exception) {
            Log::error("GeneralService::slackNotification:: {$exception->getLine()} {$exception->getMessage()}");

            return null;
        }
    }
}
