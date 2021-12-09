<?php

namespace App\Http\Controllers;

use App\Classes\SlackOnboarding;
use Illuminate\Http\Request;

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
        }

        error_log( 'Unhandled event:' );
        error_log( json_encode( $payload ) );
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

        return json_decode( $body );
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
                return (new SlackOnboarding())->invite($event->channel, $event->user);
        }

        error_log( 'Unhandled event callback:' );
        error_log( json_encode( $event ) );
        return response( '', 200 );
    }

}
