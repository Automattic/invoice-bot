<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OAuthController extends Controller
{
    
    public function redirect( \Google_Client $client) 
    {
        return redirect( $client->createAuthUrl() );
    }

    public function callback( Request $request, \Google_Client $client )
    {
        if($request->get('error')) {
            return  $request->get('error');
        }

        if($request->get('code')) {
            $client->fetchAccessTokenWithAuthCode($request->get('code'));

            $accessToken = $client->getAccessToken();

            if( $accessToken ) {
                session(['access_token' => $client->getAccessToken()]);
                return 'Access token in set!';
            }
            
            return 'Na-aa!';
        }

        abort(500);
    }
}
