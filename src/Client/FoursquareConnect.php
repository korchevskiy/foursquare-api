<?php 
namespace Foursquare\Client;

use MicroCore\Core\Registry;

/**
 * Foursquare Connect
 * 
 * @package Foursquare\Client
 * @author Sergey Korchevskiy
 * @license GPLv3 <http://www.gnu.org/licenses/gpl.txt>
 */

class FoursquareConnect extends \MicroCore\Network\Client\Connect
{
    public function __construct( &$controller, $token='')
    {
        parent::__construct($controller, $token);
        
        $this->_clientId = Registry::$settings['foursquare.client_id'];
        $this->_secret = Registry::$settings['foursquare.secret'];

        $this->callback = BASE_URL . '/users/login/foursquare';
        
        if ( ! $token && $this->_member['member_id'] && $this->_member['foursquare_token'] ) {
            $token  = $this->_member['foursquare_token'];
        }
        
        if ( ! $this->_clientId || ! $this->_secret ) {
            throw new \Exception( 'API_NOT_SET_UP' );
        }
        $this->initApi($token);
    }
    
    public function setCallback($callback)
    {
        $this->callback = $callback;
        $this->_api->setRedirectUrl($this->callback);
    }
    
    public function initApi( $token = '' )
    {
        $this->_userToken  = trim( $token );
        $this->_api = new FoursquareApi( $this->_clientId, $this->_secret, $this->_userToken, $this->callback);
        
        if ( $this->_userToken ) {
            $data = $this->_api->get('users/self');
            if ( !empty($data['response']['user']) ) {
                $this->_userData  = $data['response']['user'];
                $this->_connected = true;
            } else {
                $this->_userData  = array();
                $this->_connected = false;
            }
        } else {
            $this->_userData  = array();
            $this->_connected = false;
        }
    }
    
    
    public function redirectToConnectPage($module = 'front')
    {
        $params = array(
            'state' =>  $module  . '-' . Registry::$member['secure_hash']
        );
        if ( $module  == 'ucp' ) {
            $params['approval_prompt'] = 'force';
        }
        $url = $this->_api->getRedirectUrl($params);
        $this->_controller->redirect( $url , false );
    }
    
    public function finishLogin($module = 'front', $finalRedirect = '/')
    {
        if ( $_REQUEST['state'] !== Registry::$member['secure_hash'] ) {
            throw new \Exception( 'CREATION_FAIL' );
        }
        if ( !($response = $this->_api->getTokenByCode($_REQUEST['code'])) ) {
            throw new \Exception( 'CREATION_FAIL' );
        }
        if ( isset( $response['error'] ) || !isset( $response['access_token'] ) ) {
            pr($response);
            throw new \Exception( 'SOCIAL_NETWORK_ERROR' );
        }
        $this->_api->setAccessToken($response['access_token']);
        
        $data = $this->_api->get('users/self');
        if ( empty($data['response']['user']) ) {
            throw new \Exception( 'CREATION_FAIL' );
        }
        $userData = $data['response']['user'];
        $this->_member['foursquare_id'] = $userData['id'];
        $this->_member['foursquare_token'] = $this->_userToken = $response['access_token'];
        $userData['email'] = empty($userData['contact']['email']) ? "" : $userData['contact']['email'];
        $memberData = $this->Model->find('first', array('foursquare_id' => $this->_member['foursquare_id']));
        if ( empty($memberData) && $userData['email']) {
            $existingEmail = $this->Model->find('first', array('email' => $this->_member['email']));
            if ( !empty($existingEmail)) {
                throw new \Exception( 'MERGE_SOCIAL_ACCOUNT' );
            }
            if(empty($this->_member['member_id'])) {
                $this->_member['email'] = $userData['email'];
                $this->_member['members_display_name'] = $userData['firstName'] . ' ' . $userData['lastName'];
                $this->_member['gender'] = $userData['gender'] == 'male' ? 1 : 2;
                $this->_member['first_name'] = $userData['firstName'];
                $this->_member['last_name'] = $userData['lastName'];
                
                $this->_member['picture'] = empty($userData['photo']['suffix']) ? '' : $userData['photo']['prefix'] . $userData['photo']['suffix'];
                $memberData = $this->Model->createUserFromFoursquare($this->_member);
                if ( ! $memberData['member_id'] ) {
                    throw new \Exception( 'CREATION_FAIL' );
                }
            } else {
                $this->Model->updateFoursquareToken( $this->_member['member_id'], 
                    array( 'foursquare_id'  => $this->_member['foursquare_id'], 'foursquare_token'  => $this->_member['foursquare_token'], )
                );
            }
        } else {
            $this->Model->updateFoursquareToken( $memberData['member_id'], 
                    array( 'foursquare_token'  => $this->_member['foursquare_token'] )
            );
        }
        $this->Auth->loginWithoutCheckingCredentials($memberData['member_id']);
        $this->_controller->redirect($finalRedirect);
    }
    
    public function finishConnection($finalRedirect = '/users/foursquare')
    {
        if ($_REQUEST['m'] && $_REQUEST['code']) {
            $_member = $this->Model->getUserById( $_REQUEST['m'], array('foursquare_id', 'foursquare_token') );
            if ($_member) {
                $_urlExtra = '';
                if ( isset($_REQUEST['key'] )) {
                    $_urlExtra .= '?key=' . $_REQUEST['key'];
                }
                
                $this->_api->setRedirectUrl( $this->callback . $_urlExtra );
                
                if ( ($response = $this->_api->getTokenByCode($_REQUEST['code'])) && !isset( $response['error'] ) && isset( $response['access_token'] ) ) {
                    $this->_api->setAccessToken($response['access_token']);
                    
                    $data = $this->_api->get('users/self');
                    if ( empty($data['response']['user']) ) {
                        throw new \Exception( 'CREATION_FAIL' );
                    }
                    $this->Model->updateFoursquareToken( $_member['member_id'], 
                                array( 
                                    'foursquare_id'    => $data['response']['user']['id'], 
                                    'foursquare_token'  => $response['access_token']
                                )
                    );
                    $this->Auth->update();
                }
            }
        }
        $this->registry->redirect($finalRedirect);
    }
    
    public function isConnected()
    {
        return ( $this->_connected == true ) ? true : false;
    }
    
    public function fetchUserData()
    {
        return $this->_userData;
    }
}