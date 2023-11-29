<?php

namespace App\Http\Controllers;

use App\Classes\GoogleDrive;
use App\Classes\Invoice;
use App\Classes\Slack;
use App\Mail\InvoiceMail;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class SlackController extends Controller
{
    
    public function receiveEvent( Request $request )
    {
        $payload = $this->authenticatePayload( $request );

        switch ( $payload->type ) {
            case 'url_verification':
                return response( $payload->challenge, 200 );

            case 'event_callback':
                return $this->handleEventCallback( $payload->event );
        }

        logger( 'Unhandled event:', [$payload] );
        return response( '', 200 );
    }

    public function receiveInteraction( Request $request, \Google_Client $client )
    {
        $payload = $this->authenticateInteractionPayload( $request );
        $user = User::where('slack_user_id', $payload->user->id)->first();
        if( $user && $user->google_access_token ) {
            $client->setAccessToken( $user->google_access_token );
            $user->google_access_token = GoogleDrive::maybeRefreshAccessToken( $client );
            $user->save();
        }

        switch( $payload->type ) {
            case 'block_actions':
                return $this->handleBlockAction( $payload, $user, $client );
            case 'view_submission':
                return $this->handleViewSubmission( $payload, $user, $client );
            case 'shortcut':
                return $this->handleShortcut( $payload, $user, $client );
        }

        return response( '', 200 );
    }

    private function authenticatePayload( Request $request ) 
    {
        // Verify timestamp.
        $timestamp = intval( $request->header( 'X-Slack-Request-Timestamp' ) );
        if ( $timestamp < time() - 60 * 5 ) {
            return response( 'Timestamp too old', 400 );
        }

        // Verify signature.
        $signature = $request->header( 'X-Slack-Signature' );
        $secret = config( 'services.slack.signing_secret' );
        $body = $request->getContent();

        $hash = 'v0=' . hash_hmac( 'sha256', 'v0:' . $timestamp . ':' . $body, $secret );
        if ( ! hash_equals( $hash, $signature ) ) {
            return response( 'Invalid signature', 400 );
        }

        if ( 'application/x-www-form-urlencoded' === $request->header( 'Content-type' ) ) {
            $body = $request->input( 'payload' );
        }

        return json_decode( $body );
    }

    private function handleBlockAction( $payload, $user, $client ) {
        switch ( $payload->actions[0]->action_id ) {
            case 'save-invoice-details':
                return $this->handleSaveInvoiceDetails( $payload, $client );
            case 'submit-invoice':
                return $this->handleSubmitInvoice( $payload, $client );
            case 'next-invoice-number-action':
                return $this->handleInvoiceNumberAction( $payload );
            case 'invoice-day-action':
                return $this->handleInvoiceDayAction( $payload );
            case 'disconnect-action':
                return $this->handleDisconnectAction( $payload );
        }
    }

    private function handleViewSubmission( $payload, $user, $client ) {
        switch ( $payload->view->callback_id ) {
            case 'disconnect-confirmation-modal':
                return $this->handleDisconnectConfirmation( $payload, $user, $client );
            case 'create-invoice-modal':
                return $this->handleCreateNewInvoiceModalSubmission( $payload, $user, $client );
        }
    }

    private function handleShortcut( $payload, $user, $client ) {
        switch ( $payload->callback_id ) {
            case 'create-new-invoice':
                return $this->handleCreateNewInvoiceShortcut( $payload, $user, $client );
        }
    }

    private function authenticateInteractionPayload( Request $request ) 
    {
        // Verify timestamp.
        $timestamp = intval( $request->header( 'X-Slack-Request-Timestamp' ) );
        if ( $timestamp < time() - 60 * 5 ) {
            return response( 'Timestamp too old', 400 );
        }

        // Verify signature.
        $signature = $request->header( 'X-Slack-Signature' );
        $secret = config( 'services.slack.signing_secret' );
        $body = $request->getContent();

        $hash = 'v0=' . hash_hmac( 'sha256', 'v0:' . $timestamp . ':' . $body, $secret );
        if ( ! hash_equals( $hash, $signature ) ) {
            return response( 'Invalid signature', 400 );
        }

        return json_decode( $request->input( 'payload' ) );
    }

    private function handleEventCallback( $event )
    {
        if ( ! is_object( $event ) || empty( $event->type ) ) {
            return response( 'Invalid request', 400 );
        }

        switch ( $event->type ) {
            case 'message':
                error_log( 'Received message: ' . $event->text );
                return response( '', 200 );
            case 'app_home_opened':
                return $this->handleAppHomeOpened( $event );
        }

        error_log( 'Unhandled event callback:' );
        error_log( json_encode( $event ) );
        return response( '', 200 );
    }

    private function handleAppHomeOpened( $event )
    {
        $user = User::firstOrCreate(
            ['slack_user_id' => $event->user],
            ['slack_channel_id' => $event->channel]
        );

        $slack = new Slack( $user );
        $slackUserData = $slack->getUserInfo();

        $user->name = data_get($slackUserData, 'real_name');
        $user->timezone = data_get($slackUserData, 'tz');
        switch ($user->status) {
            case 'fresh':
                $user->email = data_get($slackUserData, 'profile.email'); // After an account is authorized, we will get the email address from google.
                $slack->publishUnauthorizedHomeView();
                $user->status = 'invited';
                break;
            case 'authorized':
                $slack->publishInvoiceSettingsHomeView();
                break;
            case 'active':
                $slack->publishActiveHomeView();
                break;
        }

        $user->save();
        
        return response( '', 200 );
    }

    private function handleSaveInvoiceDetails( $payload, $client )
    {
        $user = User::where('slack_user_id', $payload->user->id)->firstOrFail();
        $slack = new Slack($user);

        $driveSetup = new GoogleDrive($client);
        $folder = $driveSetup->createFolder();
        $template = $driveSetup->createTemplate();

        $user->gdrive_folder_id = $folder->getId();
        $user->gdrive_template_id = $template->getId();

        $formValues = collect($slack->extractFormValues($payload));
        $textReplacements = [];
        if(isset($formValues['name-action'])) {
            $textReplacements['YOUR NAME'] = $formValues['name-action'];
            $textReplacements['INITIALS'] = $this->initials( $formValues['name-action'] );
            $user->name = $formValues['name-action'];
        }
        if(isset($formValues['address-action'])) {
            $textReplacements['YOUR ADDRESS'] = $formValues['address-action'];
        }
        if(isset($formValues['tax-id-action'])) {
            $textReplacements['YOUR TAX ID'] = $formValues['tax-id-action'];
        }
        if(isset($formValues['division-action'])) {
            $textReplacements['DIVISION'] = $formValues['division-action'];
        }
        if(isset($formValues['team-action'])) {
            $textReplacements['TEAM NAME'] = $formValues['team-action'];
        }
        if(isset($formValues['amount-action'])) {
            $textReplacements['$XXX'] = $formValues['amount-action'];
            $textReplacements['$0'] = preg_replace('/(^[^0-9]*)[0-9].*/', '${1}0', $formValues['amount-action']);
        }
        if(isset($formValues['bank-name-action'])) {
            $textReplacements['BANK NAME'] = $formValues['bank-name-action'];
        }
        if(isset($formValues['iban-action'])) {
            $textReplacements['IBAN'] = $formValues['iban-action'];
        }
        if(isset($formValues['bic-action'])) {
            $textReplacements['BIC'] = $formValues['bic-action'];
        }
        $user->status = 'active';
        $user->save();

        $invoice = new Invoice($client, $template);
        if(!empty($textReplacements)) {
            $invoice->replaceText($textReplacements);
        }

        $slack->publishActiveHomeView();

        return response( '', 200 );
    }

    private function initials( $name )
    {
        $ret = '';
        foreach (explode(' ', $name) as $word) {
            $ret .= strtoupper($word[0]);
        }
        return $ret;
    }

    private function handleSubmitInvoice( $payload, $client )
    {
        $user = User::where('slack_user_id', $payload->user->id)->firstOrFail();
        $slack = new Slack($user);
        $invoiceData = json_decode($payload->actions[0]->value);

        Mail::send(new InvoiceMail($user, $invoiceData));
        
        $slack->replyMessage($payload, 'Thank you for submitting your invoice. I will send it to payroll shortly.', [
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => 'I have submitted the invoice on your beahalf!',
                    ],
                ],
                Slack::getInvoicePreviewBlock($invoiceData->invoice_number, $invoiceData->invoice_name),
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'View Invoice',
                                'emoji' => true,
                            ],
                            'url' => GoogleDrive::getDocLinkById( $invoiceData->invoice_id ),
                            'value' => 'invoice_settings',
                            'action_id' => 'invoice_settings',
                        ],
                    ],
                ]
            ],
        ]);

        return response( '', 200 );
    }

    private function handleInvoiceNumberAction( $payload )
    {
        $user = User::where('slack_user_id', $payload->user->id)->firstOrFail();
        $user->next_invoice_number = $payload->actions[0]->value;
        $user->save();

        return response( '', 200 );
    }

    private function handleInvoiceDayAction( $payload )
    {
        $user = User::where('slack_user_id', $payload->user->id)->firstOrFail();
        $user->invoice_day = $payload->actions[0]->value;

        // Adjust the next invoice due date
        $user->send_invoice_at = $user->send_invoice_at->day(min($user->invoice_day, $user->send_invoice_at->daysInMonth));

        $user->save();

        return response( '', 200 );
    }

    private function handleDisconnectAction( $payload )
    {
        $user = User::where('slack_user_id', $payload->user->id)->firstOrFail();

        $slack = new Slack($user);
        $slack->post('views.open', [
            'trigger_id' => $payload->trigger_id,
            'view' => [
                'type' => 'modal',
                'callback_id' => 'disconnect-confirmation-modal',
                'title' => [
                    'type' => 'plain_text',
                    'text' => 'Disconnect',
                ],
                'submit' => [
                    'type' => 'plain_text',
                    'text' => 'Disconnect',
                ],
                'close' => [
                    'type' => 'plain_text',
                    'text' => 'Cancel',
                ],
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => 'Are you sure? This cannot be reversed and you will need to start over fresh.',
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "I don't want to see you go :sob:",
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function handleCreateNewInvoiceShortcut( $payload, $user, $client )
    {
        $user = User::where('slack_user_id', $payload->user->id)->first();
        if(!$user || $user->status != 'active') {
            Slack::post('views.open', [
                'trigger_id' => $payload->trigger_id,
                'view' => [
                    'type' => 'modal',
                    'title' => [
                        'type' => 'plain_text',
                        'text' => 'You are not connected',
                    ],
                    'close' => [
                        'type' => 'plain_text',
                        'text' => 'Close',
                    ],
                    'blocks' => [
                        [
                            'type' => 'section',
                            'text' => [
                                'type' => 'mrkdwn',
                                'text' => 'Please open invoice bot app home to connect invoice bot',
                            ],
                        ],
                    ],
                ],
            ]);

            return response( '', 200 );
        }

        $slack = new Slack($user);
        $slack->post('views.open', [
            'trigger_id' => $payload->trigger_id,
            'view' => [
                'type' => 'modal',
                'callback_id' => 'create-invoice-modal',
                'title' => [
                    'type' => 'plain_text',
                    'text' => 'Create a custom invoice',
                ],
                'submit' => [
                    'type' => 'plain_text',
                    'text' => 'Create',
                ],
                'close' => [
                    'type' => 'plain_text',
                    'text' => 'Cancel',
                ],
                'blocks' => [
                    [
                        'type' => 'context',
                        'elements' => [
                            [
                                'type' => 'plain_text',
                                'text' => 'Use this to create a new custom invoice based on your template.',
                                'emoji' => true,
                            ],
                        ],
                    ],
                    [
                        'type' => 'input',
                        'element' => [
                            'type' => 'plain_text_input',
                            'action_id' => 'invoice-number-action',
                            'initial_value' => (string) $user->next_invoice_number,
                        ],
                        'label' => [
                            'type' => 'plain_text',
                            'text' => 'Invoice Number',
                        ],
                    ],
                    [
                        'type' => 'input',
                        'element' => [
                            'type' => 'datepicker',
                            'action_id' => 'invoice-date-action',
                            'initial_date' => today()->format('Y-m-d'),
                        ],
                        'label' => [
                            'type' => 'plain_text',
                            'text' => 'Invoice Date',
                            'emoji' => true,
                        ]
                    ]
                ],
            ],
        ]);
    }

    private function handleCreateNewInvoiceModalSubmission( $payload, $user, $client )
    {
        $slack = new Slack($user);
        $formValues = $slack->extractFormValues($payload);
        $invoiceDate = Carbon::createFromFormat('Y-m-d', $formValues['invoice-date-action']);
        $invoiceNumber = $formValues['invoice-number-action'];

        $invoice_name =  'Invoice: ' . $invoiceDate->format('d M Y');
        $invoice = Invoice::create( $client, $user->gdrive_template_id, $invoice_name );
        $invoice->replaceText([
            '{{invoiceDate}}' => $invoiceDate->toDateString(),
            '{{invoiceNumber}}' => $invoiceNumber,
            '{{invoiceYear}}' => $invoiceDate->format('Y'),
        ]);

        $slack->sendInvoiceMessage( $invoice_name, $invoiceNumber, $invoice->document->getId(), $invoiceDate->toDateString());

        // If user used the next invoice number, increment it
        if( $user->next_invoice_number == $invoiceNumber ) {
            $user->next_invoice_number++;
            $user->save();
        }

        return response( '', 200 );
    }

    private function handleDisconnectConfirmation( $payload, $user, $client )
    {
        $client->revokeToken();
        $slack = new Slack($user);
        $slack->sendMessage("Bye bye! :wave:");
        $slack->publishUnauthorizedHomeView();
        $user->delete();

        return response( '', 200 );
    }
}
