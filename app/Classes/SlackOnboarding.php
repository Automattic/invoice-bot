<?php

namespace App\Classes;

use Illuminate\Support\Facades\Http;

class SlackOnboarding
{
    public function invite($channelId, $userId)
    {
        $response = Http::withToken(config('services.slack.token'))
            ->contentType('application/json')
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channelId,
                'text' => "Welcome to the #{$channelId} channel! :tada:",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "Welcome to the #{$channelId} channel! :tada:",
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "You can invite others to this channel by typing `/invite @username`",
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "You can also invite others to this channel by sharing this link:",
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "https://slack.com/app_redirect?channel={$channelId}&user={$userId}",
                        ],
                    ],
                ],
            ]);

        return logger('message sent', $response->json());
    }
}