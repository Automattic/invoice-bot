<?php

namespace App\Http\Controllers;

use App\Classes\GoogleDrive;
use App\Classes\Invoice;
use App\Classes\Slack;
use Illuminate\Http\Request;
use App\Models\User;


class SlackController extends Controller
{
    
    public function receive_event( Request $request )
    {
        $payload = $this->authenticate_payload( $request );

        switch ( $payload->type ) {
            case 'url_verification':
                return response( $payload->challenge, 200 );

            case 'event_callback':
                return $this->handle_event_callback( $payload->event );
            
            case 'block_actions':
                return $this->handle_block_action( $payload->channel->id, $payload->actions );
        }

        error_log( 'Unhandled event:' );
        error_log( json_encode( $payload ) );
        return response( '', 200 );
    }

    public function receive_block_actions( Request $request, \Google_Client $client )
    {
        $payload = $this->authenticate_block_action_payload( $request );
        $user = User::where('slack_user_id', $payload->user->id)->firstOrFail();
        $client->setAccessToken( $user->google_access_token );
        $user->google_access_token = GoogleDrive::maybeRefreshAccessToken( $client );
        $user->save();

        switch ( $payload->actions[0]->action_id ) {
            case 'save-invoice-details':
                return $this->save_invoice_details( $payload, $client );
        }

        return response( '', 200 );
    }

    private function authenticate_payload( Request $request ) 
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

    private function handle_block_action( $channel_id, $actions ) {
        foreach ( $actions as $action ) {
            Slack::post( 'chat.postMessage', [
                'channel' => $channel_id,
                'text' => 'Received: Block ' . $action->block_id . ' Action ' . $action->action_id,
            ] );
        }
    }

    private function authenticate_block_action_payload( Request $request ) 
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

    private function handle_event_callback( $event )
    {
        if ( ! is_object( $event ) || empty( $event->type ) ) {
            return response( 'Invalid request', 400 );
        }

        switch ( $event->type ) {
            case 'message':
                error_log( 'Received message: ' . $event->text );
                return response( '', 200 );
            case 'app_home_opened':
                return $this->handle_app_home_opened( $event );
        }

        error_log( 'Unhandled event callback:' );
        error_log( json_encode( $event ) );
        return response( '', 200 );
    }

    private function handle_app_home_opened( $event )
    {
        $user = User::firstOrCreate(
            ['slack_user_id' => $event->user],
            ['slack_channel_id' => $event->channel]
        );

        

        $slack = new Slack( $user );
        switch ($user->status) {
            case 'fresh':
                $slack->publishUnauthorizedHomeView();
                $user->status = 'invited';
                $user->save();
                break;
            case 'authorized':
                $slack->publishInvoiceSettingsHomeView();
                break;
            case 'active':
                $slack->publishActiveHomeView();
                break;
        }
        
        return response( '', 200 );
    }

    private function save_invoice_details( $payload, $client )
    {
        $user = User::where('slack_user_id', $payload->user->id)->firstOrFail();
        $slack = new Slack($user);

        $driveSetup = new GoogleDrive($client);
        $folder = $driveSetup->createFolder();
        $template = $driveSetup->createTemplate();

        $user->gdrive_folder_id = $folder->getId();
        $user->gdrive_template_id = $template->getId();
        $user->status = 'active';
        $user->save();

        $formValues = collect($slack->extractFormValues($payload));
        $textReplacements = [];
        if(isset($formValues['name-action'])) {
            $textReplacements['YOUR NAME'] = $formValues['name-action'];
            $textReplacements['INITIALS'] = $this->initials( $formValues['name-action'] );
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

        $invoice = new Invoice($client, $template);
        $invoice->replaceText($textReplacements);

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
}
