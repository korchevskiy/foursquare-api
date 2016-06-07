<?php 
namespace Foursquare\Client;

use MicroCore\Core\Registry;

/**
 * FoursquareApi
 * A PHP-based Foursquare client library
 * 
 * @package Foursquare\Client
 * @author Sergey Korchevskiy
 * @license GPLv3 <http://www.gnu.org/licenses/gpl.txt>
 */
class FoursquareApi
{
    /**
     * Maps aliases to Foursquare urls.
     */
    public static $URL_MAP = array(
        'base'          => 'https://api.foursquare.com/v2',
        'authenticate'  => 'https://foursquare.com/oauth2/authenticate',
        'authorize'     => 'https://foursquare.com/oauth2/authorize',
        'request_token' => 'https://foursquare.com/oauth2/access_token'
    );
    /** 
      * @var String $version YYYYMMDD
      * https://developer.foursquare.com/overview/versioning
      */
    public $version = '20140806';

    /** @var String $сlientId */
    protected $_сlientId;
    /** @var String $clientSecret */
    protected $_clientSecret;
    /** @var String $callback */
    protected $_callback;
    /** @var String $token */
    protected $_token;
    /** @var String $locale */
    protected $_locale;
    
    
    /**
     * Constructor for the API
     * Prepares the request URL and client api params
     * @param bool|String $clientId
     * @param bool|String $clientSecret
     * @param String $token
     * @param String $callback
     * @param String $locale
     */
    public function __construct($clientId, $clientSecret, $token = '', $callback = '', $locale = 'en')
    {
        $this->_clientId = $clientId;
        $this->_clientSecret = $clientSecret;
        $this->_locale = $locale;
        $this->_token = $token;
        $this->_callback = $callback;
    }
    
    /**
     * setRedirectUrl
     * @param String $callback
     */
    public function setRedirectUrl($callback)
    {
        $this->_callback = $callback;
    }
    
    /**
     * setAccessToken
     * Basic setter function, provides an authentication token to "get" requests
     * @param String $token A Foursquare user auth_token
     */
    public function setAccessToken($token)
    {
        $this->_token = $token;
    }
    
    /**
     * api
     * @param String $method - posible values: get, post, put, delete
     * @param String $api - Foursquere endpoint
     * @param Array $data
     * @return Array|boolean
     */
    public function api($method, $api, $data = array())
    {
        switch(strtolower($method)) {
            case 'delete':
                $method = 'DELETE';
                break;
            case 'put':
                $method = 'PUT';
                break;
            case 'post':
                $method = 'POST';
                break;
            case 'get':
            default:
                $method = 'GET';
                break;
        }
        $HttpRequest = Registry::init('HttpRequest');
        $HttpRequest->clearHeaders();
        
        $data = array_merge(array('v' => $this->version), $data);
        
        $result = $HttpRequest->makeRequest(self::$URL_MAP['base'] . '/' . $api, $data, $method);
        if(empty($result) || !($result = json_decode($result, true))) {
            return false;
        }
        
        return $result;
    }
    
    /** 
     * getPublic
     * Performs a request for a public resource
     * @param String $api - a particular endpoint of the Foursquare API
     * @param Array $data - set of parameters to be appended to the request
     * @return Array|boolean
     */
    public function getPublic($api, $data = array())
    {
        $data = array_merge(array(
                'client_id'     => $this->_clientId,
                'client_secret' => $this->_clientSecret,
                'locale'        => $this->_locale,
            ), $data);
        return $this->api('get', $api, $data);
    }
    
    /** 
     * get
     * Performs a request for a public resource
     * @param String $api - a particular endpoint of the Foursquare API
     * @param Array $data - set of parameters to be appended to the request
     * @return Array|boolean
     */
    public function get($api, $data = array())
    {
        $data = array_merge(array(
                'oauth_token'   => $this->_token,
                'locale'        => $this->_locale,
            ), $data);
        return $this->api('get', $api, $data);
    }
    
    /** 
     * postPublic
     * Performs a request for a public resource
     * @param String $api - a particular endpoint of the Foursquare API
     * @param Array $data - set of parameters to be appended to the request
     * @return Array|boolean
     */
    public function postPublic($api, $data = array())
    {
        $data = array_merge(array(
                'client_id'     => $this->_clientId,
                'client_secret' => $this->_clientSecret,
                'locale'        => $this->_locale,
            ), $data);
        return $this->api('post', $api, $data);
    }
    
    /** 
     * post
     * Performs a request for a public resource
     * @param String $api - a particular endpoint of the Foursquare API
     * @param Array $data - set of parameters to be appended to the request
     * @return Array|boolean
     */
    public function post($api, $data = array())
    {
        $data = array_merge(array(
                'oauth_token'   => $this->_token,
                'locale'        => $this->_locale,
            ), $data);
        return $this->api('post', $api, $data);
    }
    

    /**
     * getMulti
     * Performs a request for up to 5 private or public resources
     * @param Array $requests An array of arrays containing the api endpoint and a set of parameters
     * to be appended to the request, defaults to false (none)
     * @param Array $data 
     * It does not allow you to call endpoints that mutate data.
     * @return Array|boolean
     */
    public function getMulti($requests, $data = array())
    {
        if (!is_array($requests)) {
            return false;
        }
        $queries = array();
        foreach($requests as $request) {
            $query = '/' . $request['api'];
            unset($request['api']);
            if (!empty($request)) {
              $query .= '?' . http_build_query($request);  
            }
            $queries[] = $query;
        }
        $data = array_merge(array(
                'oauth_token'   => $this->_token,
                'locale'        => $this->_locale,
                'requests'      => $queries
            ), $data);
            
        return $this->api('get', 'multi', $data);
    }

    /**
     * getRedirectUrl
     * Returns a link to the Foursquare web authentication page.
     * @param Array $params
     * @return String
     */
    public function getRedirectUrl($params = array())
    {
        $params = array_merge(array(
            'response_type' => 'code',
            'client_id'     => $this->_clientId,
            'redirect_uri'  => $this->_callback
            ), $params);
        
        return self::$URL_MAP['authenticate'] . '?' . http_build_query($params);
    }
    
    /**
     * getAuthorizeUrl
     * Returns a link to the Foursquare web authentication page. Using /authorize will ask the user to
     * re-authenticate their identity and reauthorize your app while giving the user the option to
     * login under a different account.
     * @param Array $params
     * @return String
     */
    public function getAuthorizeUrl($params = array())
    {
        $params = array_merge(array(
            'response_type' => 'code',
            'client_id'     => $this->_clientId,
            'redirect_uri'  => $this->_callback
            ), $params);
        
        return self::$URL_MAP['authorize'] . '?' . http_build_query($params);
    }
    
    /**
     * getTokenByCode
     * Performs a request to Foursquare for a user token, and returns the token, while also storing it
     * locally for use in private requests
     * @param $code The 'code' parameter provided by the Foursquare webauth callback redirect
     * @return Array|boolean
     */
    public function getTokenByCode($code)
    {
        $HttpRequest = Registry::init('HttpRequest');
        $response = $HttpRequest->makeRequest( self::$URL_MAP['request_token'], array(
            'code'          => $code,
            'client_id'     => $this->_clientId,
            'client_secret' => $this->_clientSecret,
            'redirect_uri'  => $this->_callback,
            'grant_type'    => 'authorization_code',
        ), 'POST' );
        if ($response && ( $response = json_decode( $response, true ) ) ) {
            return $response;
        }
        return false;
    }
}