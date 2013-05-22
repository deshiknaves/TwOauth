<?php
/**
 * @file
 * A class to connect to twitter oAuth
 */

class twOauth
{
    
    const REQUEST_URL  = "https://twitter.com/oauth/request_token";
    const ACCESS_URL   = 'https://api.twitter.com/oauth/access_token';
    const OAUTH_METHOD = 'HMAC-SHA1';
    const CONFIG_PATH  = 'TwOauthKeys.json';
    
    private $oauth     = array();
    private $oauth_token_secret;
    private $consumer_secret;
    private $oauth_callback;


    /**
     * Constructor, set up required data
     * @param (string) $oauth_token        The oAuth token if there is one
     * @param (string) $oauth_token_secret The oAuth secret, if there is one
     * @param (string) $version            oAuth version
     */
    public function __construct($oauth_token = null, $oauth_token_secret = null, $version = '1.0a')
    {

        // Get the keys from the config
        $config = $this->getConfig();

        // Error checking
        if ( ! isset($config->consumer_key) ||
             ! isset($config->consumer_secret) ||
             empty($config->consumer_key) ||
             empty($config->consumer_secret)
        ) {
            throw new Exception("Error Required Keys missing. Please add 
                the keys to keys file", 1);       
        }

        // Check to see if the session names are set
        if (isset($config->session_oauth_token_name) &&
            ! empty($config->session_oauth_token_name) &&
            ! $oauth_token &&
            isset($_SESSION[$config->session_oauth_token_name])) {
            $oauth_token = $_SESSION[$config->session_oauth_token_name];
        }

        if (isset($config->session_oauth_token_secret_name) &&
            ! empty($config->session_oauth_token_secret_name) &&
            ! $oauth_token_secret &&
            isset($_SESSION[$config->session_oauth_token_secret_name])) {
            $oauth_token_secret = $_SESSION[$config->session_oauth_token_secret_name];
        }        

        // Set up the properties
        $this->oauth['oauth_consumer_key']     = $config->consumer_key;
        $this->oauth['oauth_version']          = $version;
        $this->oauth['oauth_signature_method'] = static::OAUTH_METHOD;
        $this->oauth['oauth_token']            = $oauth_token;
        $this->oauth_token_secret              = $oauth_token_secret;
        $this->consumer_secret                 = $config->consumer_secret;

        if (isset($config->oauth_callback)) {
            $this->oauth_callback = $config->oauth_callback;
        }
    }


    /**
     * Get keys from the keys file
     * @return (stdClass) The decoded JSON object
     */
    private function getConfig()
    {
        $path = static::CONFIG_PATH;
        if ( ! file_exists(static::CONFIG_PATH)) {
            $path = dirname(__FILE__).'/'.static::CONFIG_PATH;
        }

        if ( ! file_exists($path)) {
            throw new Exception("Error: Could not find keys file at $path.", 1);
        }

        $file = file_get_contents($path);
        return json_decode($file);
    }


    /**
     * Return the oAuth parameters for a request
     * @param  (array) $options Additional options required in the oAuth
     * @return (array)          The oAuth array
     */
    public function getOauth($options = array())
    {
        $oauth = $this->oauth;

        // Add the additional items
        foreach ($options as $key => $value) {
            $oauth[$key] = $value;
        }

        $oauth['oauth_nonce']     = sha1(time().uniqid());
        $oauth['oauth_timestamp'] = time();

        return $oauth;
    }


    /**
     * Get a request token
     * @return (string) The query string for authorization
     */
    public function getRequestToken($callback = null)
    {
        $params = $this->getOauth();

        // Use default if not set
        if ( ! $callback) {
            $callback = $this->oauth_callback;
        }
        
        // Incase a callback isn't needed
        if ( ! empty($callback)) {
            $params['oauth_callback'] = $callback;
        }

        // Auth token will be set
        unset($params['oauth_token']);

        // Create oAuth Signature
        $params['oauth_signature'] = $this->buildSignature('GET', $params, static::REQUEST_URL);

        $headers = $this->buildHeaders($params);
        $url = $this->prepareUrl($params, static::REQUEST_URL);

        // Make the request
        return $this->request($url);          
    }

    /**
     * Set the oauth tokens. Might be useful if using
     * the object after getting the access tokens from the
     * same script
     * @param (string) $oauth_token        The oAuth token
     * @param (string) $oauth_token_secret The oAuth token secret
     * @return (DeshOauth)                 Returns the instance
     */ 
    public function setTokens($oauth_token, $oauth_token_secret)
    {
        $this->oauth['oauth_token'] = $oauth_token;
        $this->oauth_token_secret   = $oauth_token_secret;

        return $this;
    }



    /**
     * Get the access keys for an account
     * @return (string) JSON response
     */
    function getAccessKey($oauth_verifier)
    {
        $params = $this->getOauth();

        $params['oauth_signature'] = $this->buildSignature('POST', $params, static::ACCESS_URL);

        $headers = $this->buildHeaders($params);
        $url     = $this->prepareUrl($params, static::ACCESS_URL);
        $data    = array('oauth_verifier' => $oauth_verifier);

        return $this->request($url, $headers, $data);
    }


    /**
     * GET a request to twitter
     * @param  (string) $url  The url to be queried
     * @param  (array) $data  The additional queires array
     * @return (string)       The JSON response
     */
    function get($url, $data = array())
    {

        $params = $this->getOauth($data);

        $params['oauth_signature'] = $this->buildSignature('GET', $params, $url);
        
        $headers = $this->buildHeaders($params);
        $url     = $this->prepareUrl($data, $url);

        return $this->request($url, $headers);
    }


    /**
     * POST a request to twitter
     * @param  (string) $url  The url to be queried
     * @param  (array) $data  The additional queires array
     * @return (string)       The JSON response
     */
    function post($url, $data = array())
    {   
        $params = $this->getOauth($data);

        $params['oauth_signature'] = $this->buildSignature('POST', $params, $url);
        
        $headers = $this->buildHeaders($params);

        return $this->request($url, $headers, $data);
    }


    /**
     * Build a URL from the params
     * @param  (array)  $params The params
     * @param  (string) $url    The url
     * @return (string)         The prepared url
     */
    private function prepareUrl($params, $url)
    {
        if ( ! empty($params)) {

            ksort($params);
            
            // Uglyfy the params for url string
            foreach ($params as $key => $value) {
                $urlPairs[] = $key."=".urlencode($value);
            }
     
            $urlParams = implode('&', $urlPairs);
            $url       = $url."?".$urlParams;
        }

        return $url;
    }


    /**
     * Build the headers for the execution
     * @param  (array) $params The array of parameters
     * @return (string)        The cURL header
     */
    private function buildHeaders($params)
    {
        // Sort the headers
        ksort($params);

        $oauth_header = '';

        // Loop though all the keys
        foreach ($params as $key => $value) {
            $oauth_header .= $key.'="'.$value.'", ';
        }

        $curl_header = array(
            "Authorization: Oauth {$oauth_header}",
            'Expect:',
        );

        return $curl_header;
    }


    /**
     * Build the signature
     * @param  (array) $params The params
     * @return (string)        The built signature
     */
    private function buildSignature($type, $params, $url)
    {
        $keys   = $this->encode(array_keys($params));
        $values = $this->encode(array_values($params));
        $params = array_combine($keys, $values);
        ksort($params);

        // Uglify the params for the string
        foreach ($params as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $paramString = implode('&', $pairs);

        // Generate Base and Secret
        $base   = $type."&".$this->encode($url)."&".$this->encode($paramString);
        $secret = $this->encode($this->consumer_secret)."&".$this->encode($this->oauth_token_secret);

        return $this->encode(base64_encode(hash_hmac('sha1', $base, $secret, TRUE)));
    }


    /**
     * Send a request
     * @param  (string) $url    The URL
     * @param  (string) $header The headers
     * @param  (string) $data   The data to be posted
     * @return (response)       The response
     */
    private function request($url, $header = null, $data = null)
    {       

        $ch = curl_init();

        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $this->http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->last_api_call = $url;
        curl_close($ch);

        return $response;
    }


    /**
     * Encode the urls for the authentication
     * @param  (string/array) $input The input
     * @return (string)              The encoded string
     */
    private function encode($input)
    {
        if (is_array($input)) {
            return array_map(array('twOauth', 'encode'), $input);
        }
        elseif (is_scalar($input)) {
            return rawurlencode($input);
        }
        else{
            return '';
        }
    }
}