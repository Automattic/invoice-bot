<?php

namespace App\Classes;

use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class Slack
{
    private $user;
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public static function post( $endpoint, $body )
    {
        $response = Http::withToken( config('services.slack.token') )
            ->contentType('application/json')
            ->post( 'https://slack.com/api/' . $endpoint, $body );

        if ( $response->getStatusCode() !== 200 ) {
            throw new \Exception( $response->getBody() );
        }

        return $response->json();
    }


    public function sendMessage($text, $payload)
    {
        $message = array_merge([
            'channel' => $this->user->slack_channel_id,
            'text' => $text,
            'blocks' => [],
        ], $payload);

        return $this->post('chat.postMessage', $message);
    }

    public function publishView($type, $blocks)
    {
        return $this->post('views.publish', [
                'user_id' => $this->user->slack_user_id,
                'view' => [
                    'type' => $type,
                    'blocks' => $blocks,
                ],
            ]);
    }

    public function publishUnauthorizedHomeView()
    {
        return $this->publishView('home', [
            [
                "type" => "section",
                "text" => [
                    "type" => "plain_text",
                    "text" => "Welcome to invoice bot! :tada:",
                    "emoji" => true
                ]
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "If you are an international contractor with :a8c: and need to send invoice at the end of every month, invoice bot can help you. :angel:"
                ]
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "Start by giving us write access to your google drive. We will use use this to store the invoice template and the invoices we will create every month. We will not have access to your other files."
                ]
            ],
            [
                'type' => 'divider'

            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Authorize',
                            'emoji' => true
                        ],
                        'value' => 'click_me_123',
                        'action_id' => 'authorize-action',
                        'url' => URL::signedRoute('oauth.redirect', ['user' => $this->user]),
                    ]
                ]
            ]
        ]);
    }

    public function publishInvoiceSettingsHomeView()
    {
        return $this->publishView('home', [
            [
              'type' => 'section',
              'text' => [
                'type' => 'plain_text',
                'text' => 'You have successfully authorized Invoice Bot! ',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'section',
              'text' => [
                'type' => 'plain_text',
                'text' => 'Let us setup an invoice template for you. Please fill out the details below to create your invoice template.',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'header',
              'text' => [
                'type' => 'plain_text',
                'text' => 'Basic Details',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'divider',
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'name-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Your full name',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'multiline' => true,
                'action_id' => 'address-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Your Address',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'tax-id-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Tax ID',
                'emoji' => false,
              ],
            ],
            [
              'type' => 'header',
              'text' => [
                'type' => 'plain_text',
                'text' => 'Work Details',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'context',
              'elements' => [
                [
                  'type' => 'plain_text',
                  'text' => 'These will not be stored in the app. I will create a google doc on your account and put these information there.',
                  'emoji' => true,
                ],
              ],
            ],
            [
              'type' => 'divider',
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'division-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Division Name',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'team-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Team Name',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'amount-action',
                'placeholder' => [
                    'type' => 'plain_text',
                    'text' => '$XXX.XX',
                ]
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Monthly Retainer Amount',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'header',
              'text' => [
                'type' => 'plain_text',
                'text' => 'Payment Information',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'context',
              'elements' => [
                [
                  'type' => 'plain_text',
                  'text' => 'These are also for the invoice template.',
                  'emoji' => true,
                ],
              ],
            ],
            [
              'type' => 'divider',
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'bank-name-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Bank Name',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'iban-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'IBAN / Account Number',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'input',
              'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'bic-action',
              ],
              'label' => [
                'type' => 'plain_text',
                'text' => 'Swift / BIC code',
                'emoji' => true,
              ],
            ],
            [
              'type' => 'actions',
              'elements' => [
                0 => [
                  'type' => 'button',
                  'text' => [
                    'type' => 'plain_text',
                    'text' => 'Save',
                    'emoji' => true,
                  ],
                  'value' => 'click_me_123',
                  'action_id' => 'save-invoice-details',
                ],
              ],
            ],
          ]);
    }

    public function publishActiveHomeView( )
    {
        return $this->publishView('home', [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'plain_text',
                    'text' => 'You have successfully authorized Invoice Bot! 🎉',
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'plain_text',
                    'text' => 'On the second last day of every month I will create a new invoice and remind you to send it. :money_mouth_face:',
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => 'All your invoices will be based on a google doc template. You can edit it any time and I will make sure to use the updated version.',
                ],
                'accessory' => [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'Edit template',
                        'emoji' => true,
                    ],
                    'value' => 'click_me_123',
                    'url' => 'https://docs.google.com/document/d/'.$this->user->gdrive_template_id.'/edit',
                    'action_id' => 'button-action',
                ],
            ],
        ]);
    }

    public function extractFormValues($payload)
    {
        $rt = [];
        foreach ((array) data_get($payload, 'view.state.values') as $values) {
            foreach ((array) $values as $key => $value) {
                $rt[$key] = data_get($value, 'value');
            }
        }

        return $rt;
    }
}
