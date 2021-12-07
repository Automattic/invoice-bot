<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SlackController extends Controller
{
    
    public function receive_event( Request $request )
    {
        $payload = json_decode( $request->getContent() );
        if ( ! is_object( $payload ) || empty( $payload->type ) ) {
            return response( 'Invalid request', 400 );
        }

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

    private function handle_event_callback( $event )
    {
        if ( ! is_object( $event ) || empty( $event->type ) ) {
            return response( 'Invalid request', 400 );
        }

        switch ( $event->type ) {
            case 'message':
                error_log( 'Received message: ' . $event->text );
                return response( '', 200 );
        }

        error_log( 'Unhandled event callback:' );
        error_log( json_encode( $payload ) );
        return response( '', 200 );
    }

}
