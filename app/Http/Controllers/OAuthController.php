<?php

namespace App\Http\Controllers;

use App\Classes\Slack;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class OAuthController extends Controller
{   
    public function redirect( \Google_Client $client, User $user )
    {
        session()->put( 'user', $user );
        $client->setState($user->slack_user_id);
        return redirect( $client->createAuthUrl() );
    }

    public function callback( Request $request, \Google_Client $client )
    {
        if($request->get('error')) {
            return  $request->get('error');
        }

        if(!Session::has('user')) {
            return 'Cannot validate the user. Please click the Authorize button on slack again.';
        }

        $user = Session::get('user');
        $slack = new Slack( $user );

        if($request->get('code')) {
            $accessToken = $client->fetchAccessTokenWithAuthCode($request->get('code'));

            if( $accessToken ) {
                $user->google_access_token = $accessToken;
                $user->email = $this->getUserEmail($client);
                $user->status = 'authorized';
                $user->save();

                $slack = new Slack( $user );
                $slack->publishInvoiceSettingsHomeView();

                return redirect('https://slack.com/app_redirect?channel='.$user->slack_channel_id);
            }
            
            return 'Some unknown error occured during authorization. Please try again.';
        }

        abort(500);
    }

    private function getUserEmail($client)
    {
        $service = new \Google\Service\PeopleService($client);
        $result = $service->people->get('people/me', ['personFields' => 'emailAddresses']);
        return $result->getEmailAddresses()[0]->getValue();
    }
}
