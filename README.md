### Usage

This library wrappers for php applications to make requests to both public and authenticated-only resources on Foursquare. 

#### Querying the API
```php
    $Foursquare = new \Foursquare\Client\FoursquareApi('<your client key>", "<your client secret>');
    $response = $Foursquare->getPublic("venues/search",array("near"=>"Montreal, Quebec"));
    pr($response);
```

#### Authenticating the user (simple)
```php
// Generates an authentication link for you to display to your users
// (https://foursquare.com/oauth2/authenticate?...)
$auth_link = $Foursquare->getRedirectUrl();

// Converts an authentication code (sent from foursquare to your $redirect_url) into an access token
if ( ($response = $Foursquare->getTokenByCode($_REQUEST['code'])) && !isset( $response['error'] ) && isset( $response['access_token'] ) ) {
    $token = $response['access_token'];
    $Foursquare->setAccessToken($token);
}
```

#### Authenticating the user in MicroCore App
```php
$connect = new \Foursquare\Client\FoursquareConnect($this);
if ( !empty($this->request['code']) ) {
    if ( $this->request['state'] ) {
        $base = explode( '-', $this->request['state'] );
        if(count($base) == 2){
            $this->request['state'] = $_REQUEST['state'] = $base[1];
            try {
                $connect->finishLogin($base[0]);
            } catch( Exception $error ) {
                $msg = $error->getMessage();
                switch( $msg ) {
                    default:
                        $this->View->set('error', 'Error 999990. No data received from foursquare, please register');
                        break;
                    case 'API_NOT_SET_UP':
                        $this->View->set('error', 'Error 999991. No data received from foursquare, please register');
                        break;
                    case 'NOT_REMOTE_MEMBER':
                        $this->View->set('error', 'Error 999992. No data received from foursquare, please register ');
                    break;
                    case 'CREATION_FAIL':
                        $this->View->set('error', 'Error 999993. No data received from foursquare, please register ');
                    break;
                    case 'CREATION_FAIL_TOKEN':
                        $this->View->set('error', 'Error 999994. No data received from foursquare, please register ');
                    break;
                    case 'MERGE_SOCIAL_ACCOUNT':
                        $this->View->set('error', 'Error 999995. Another user already linked his profile with this Google Account');
                    break;
                    case 'SOCIAL_NETWORK_ERROR':
                        $this->View->set('error', 'Error 999996. Foursquare has returned error');
                    break;
                }
                $this->View->errorAction('custom_error');
            }
        } else {
            $this->redirect("/login");
        }
    } else {
        $connect->finishConnection();
    }
} else {
    $connect->redirectToConnectPage();
}
$this->redirect("/login");
```