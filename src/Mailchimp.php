<?php

namespace Mbarwick83\Mailchimp;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Mailchimp
{
    const API_HOST = 'https://<$dc>.api.mailchimp.com/3.0/';
    const LOGIN_HOST = 'https://login.mailchimp.com/';
    const TIMEOUT = 4.0;

    protected $client;
    protected $client_id;
    protected $client_secret;

    public function __construct()
    {
        $this->client_id = config('mailchimp.client_id');
        $this->client_secret = config('mailchimp.client_secret');
        $this->redirect_uri = config('mailchimp.redirect_uri');
    }

    /**
     * Create client instance
     * 
     * @param   [string] $base_uri
     * @return  Response
     */
    protected function client($base_uri, $data_center = false)
    {
        return new Client([
            'base_uri' => $data_center ? str_replace('<$dc>', $data_center, $base_uri) : $base_uri,
            'timeout'  => self::TIMEOUT,
        ]);     
    }

    /**
    * Get authorization url for oauth
    * 
    * @return   String
    */
    public function getLoginUrl()
    {
	   return $this->url('oauth2/authorize', self::LOGIN_HOST);
    }

    /**
    * Get user's access token
    * 
    * @param    string $code 
    * @return   Response
    */
    public function getAccessToken($code)
    {
	   $response = $this->post('oauth2/token', ['code' => $code], true);
       return $response['access_token'];
    }

    /**
     * Get user details from access token
     *
     * @param   (string) $access_token
     * @return  Response
     */
    public function getAccountDetails($access_token)
    {
        $client = $this->client(self::LOGIN_HOST);
        $response = $this->toArray($client->request('GET', 'oauth2/metadata', [
            'headers' => ["Authorization" => "OAuth $access_token"]
        ]));

        return array_merge($response, ['access_token' => $access_token]);
    }

    /**
    * Make URLs for user browser navigation.
    *
    * @param    string $path
    * @param    string $host [base url]
    * @param    array  $parameters
    * @return   Response
    */
    protected function url($path, $host, array $parameters = null)
    {
    	$query = [
            'client_id' => $this->client_id,
    	    'client_secret' => $this->client_secret,
    	    'response_type' => 'code'
    	];

        if ($parameters)
            $query = array_merge($query, $parameters);

        $query = http_build_query($query);

        return sprintf('%s%s?%s', $host, $path, $query);
    }

    /**
    * Make POST calls to the API
    * 
    * @param    string  $path
    * @param    array   $parameters       [Optional query parameters]          
    * @param    boolean $authorization    [Use access token query params]
    * @param    string  $data_center      [user's data center code]
    * @return   Response
    */    
    public function post($path, array $parameters, $authorization = false, $data_center = false)
    {
    	$query = [];

    	if ($authorization)
    	    $query = [
    	        'client_id' => $this->client_id,
    	    	'client_secret' => $this->client_secret,			 
    	    	'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirect_uri
    	    ];       

    	if ($parameters)
            $query = array_merge($query, $parameters);

        try {
            $client = $this->client(($authorization) ? self::LOGIN_HOST : self::API_HOST, $data_center);
            $response = $client->request('POST', $path, [
                'form_params' => $query
            ]);  	    

            return $this->toArray($response);
    	} 
    	catch (ClientException $e) {
    	    return $this->toArray($e->getResponse());
        }    	
    }

    /**
    * Make GET calls to the API
    * 
    * @param    string $path
    * @param    array  $parameters   [Query parameters]
    * @param    string $data_center  [user's data center code]
    * @return   Response
    */
    public function get($path, array $parameters, $data_center)
    {
        try {
            $client = $this->client(self::API_HOST, $data_center);
    	    $response = $client->request('GET', $path, [
    	        'query' => $parameters
    	    ]);

            return $this->toArray($response);
    	}
    	catch (ClientException $e) {
    	    return $this->toArray($e->getResponse());
    	}
    }

    /**
    * Make DELETE calls to the API
    * 
    * @param    string  $path
    * @param    array   $parameters  [Optional query parameters]
    * @param    string  $data_center [user's data center code]
    * @return   Response
    */
    public function delete($path, array $parameters, $data_center)
    {
        try {
            $client = $this->client(self::API_HOST, $data_center);
            $response = $client->request('DELETE', $path, [
                'query' => $parameters
            ]);

            return $this->toArray($response);
        }
        catch (ClientException $e) {
            return $this->toArray($e->getResponse());
        } 
    }

    /**
    * Convert API response to array
    * 
    * @param    Object $response
    * @return   Response
    */
    protected function toArray($response)
    {
    	return json_decode($response->getBody()->getContents(), true);
    }    
}




