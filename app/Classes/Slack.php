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

  public static function get($endpoint, $args)
  {
    $query = http_build_query($args);

    $response = Http::withToken(config('services.slack.token'))
      ->contentType('application/json')
      ->get('https://slack.com/api/' . $endpoint . '?' . $query);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception($response->getBody());
    }

    return $response->json();
  }

  public static function post($endpoint, $body)
  {
    $response = Http::withToken(config('services.slack.token'))
      ->contentType('application/json')
      ->post('https://slack.com/api/' . $endpoint, $body);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception($response->getBody());
    }

    return $response->json();
  }

  public static function postToUrl($url, $body)
  {
    $response = Http::withToken(config('services.slack.token'))
      ->contentType('application/json')
      ->post($url, $body);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception($response->getBody());
    }

    return $response->json();
  }

  public function replyMessage($message, $text, $payload = [])
  {
    $payload['text'] = $text;
    $response = self::postToUrl($message->response_url, $payload);

    return $response;
  }

  public function sendMessage($text, $payload = [])
  {
    $message = array_merge([
      'channel' => $this->user->slack_channel_id,
      'text' => $text,
      'blocks' => [],
    ], $payload);

    return $this->post('chat.postMessage', $message);
  }

  public function sendInvoiceMessage($invoice_name, $invoice_number, $invoice_id)
  {
    $invoice_url = GoogleDrive::getDocLinkById($invoice_id);

    $this->sendMessage("I've prepared a new Invoice for you! Please review it and click Submit when ready.", [
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "I've prepared a <$invoice_url|new Invoice> for you! Please review it and send it when ready.",
                ],
            ],
            Slack::getInvoicePreviewBlock( $invoice_number, $invoice_name  ),
            [
                'type' => 'actions',
                'block_id' => 'invoice_actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Review',
                        ],
                        'value' => 'review',
                        'url' => $invoice_url,
                        'action_id' => 'review-invoice',
                    ],
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Send Invoice',
                        ],
                        'style' => 'primary',
                        'value' => json_encode(['invoice_number' => $invoice_number, 'invoice_id' => $invoice_id, 'invoice_name' => $invoice_name]),
                        'action_id' => 'submit-invoice',
                    ]
                ]
            ]
        ]
    ]);
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

  public function unauthorizedHomeView()
  {
    return [
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
    ];
  }

  public function publishUnauthorizedHomeView()
  {
    return $this->publishView('home', $this->unauthorizedHomeView());
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
          'text' => 'Let me setup an invoice template for you. Please fill out the details below to create your invoice template.',
          'emoji' => true,
        ],
      ],
      [
        "type" => "context",
        "elements" => [
          [
            "type" => "plain_text",
            "text" => "Feel free to skip any of these information now. You will be able to edit the template later.",
            "emoji" => true
          ]
        ]
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
          'initial_value' => $this->user->name,
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
        "type" => "context",
        "elements" => [
          [
            "type" => "plain_text",
            "text" => "For the invoice.",
            "emoji" => true
          ]
        ]
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
          [
            'type' => 'button',
            'style' => 'primary',
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

  public function publishActiveHomeView()
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
          'style' => 'primary',
          'text' => [
            'type' => 'plain_text',
            'text' => 'Edit template',
            'emoji' => true,
          ],
          'value' => 'click_me_123',
          'url' => 'https://docs.google.com/document/d/' . $this->user->gdrive_template_id . '/edit',
          'action_id' => 'button-action',
        ],
      ],
      [
        'type' => 'divider',
      ],
      [
        'type' => 'section',
        'text' => [
          'type' => 'plain_text',
          'text' => "Don't want your invoice numbers to start from 1? Use the field below to set your next invoice number.",
        ],
      ],
      [
        'dispatch_action' => true,
        'type' => 'input',
        'element' => [
          'type' => 'plain_text_input',
          'action_id' => 'next-invoice-number-action',
          'initial_value' => (string) $this->user->next_invoice_number,
        ],
        'label' => [
          'type' => 'plain_text',
          'text' => 'Next Invoice Number',
          'emoji' => true,
        ],
      ],
      [
        'type' => 'divider',
      ],
      [
        'type' => 'section',
        'text' => [
          'type' => 'plain_text',
          'text' => 'If you no longer want to use invoice bot, click the disconnect button and all data wil be deleted from the app.',
        ],
        'accessory' => [
          'type' => 'button',
          'style' => 'danger',
          'text' => [
            'type' => 'plain_text',
            'text' => ':disappointed: Disconnect',
            'emoji' => true,
          ],
          'value' => 'click_me_123',
          'action_id' => 'disconnect-action',
        ],
      ]
    ]);
  }

  public function extractFormValues($payload)
  {
    $rt = [];
    foreach ((array) data_get($payload, 'view.state.values') as $values) {
      foreach ((array) $values as $key => $value) {
        switch (data_get($value, 'type')) {
          case 'plain_text_input':
            $rt[$key] = data_get($value, 'value');
            break;
          case 'datepicker':
            $rt[$key] = data_get($value, 'selected_date');
            break;
        }
      }
    }

    return $rt;
  }

  public static function getInvoicePreviewBlock($invoiceNumber, $invoiceName)
  {
    return [
      "type" => "section",
      "text" => [
        "type" => "mrkdwn",
        "text" => "*Invoice#:* $invoiceNumber\n\n*Document Name:*\n $invoiceName"
      ],
      "accessory" => [
        "type" => "image",
        "image_url" => url("images/invoice.png"),
        "alt_text" => "invoice thumbnail"
      ]
    ];
  }

  public function getUserInfo()
  {
    $response = $this->get('users.info', [
      'user' => $this->user->slack_user_id,
    ]);

    return $response['user'];
  }
}
