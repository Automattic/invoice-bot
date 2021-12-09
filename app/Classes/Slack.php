<?php

namespace App\Classes;

use Illuminate\Support\Facades\Http;

class Slack
{
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

}