<?php

if(!function_exists('curl_init')) {
	throw new Exception('WireIO requires the CURL extension.');
}

if(!function_exists('json_decode')) {
	throw new Exception('WireIO requires the JSON extension.');
}

/* RestClient Copyright (c) 2011 Travis Dent <tcdent@gmail.com> */
/* Github: https://github.com/tcdent/php-restclient */
class RestClient {
    
    public $options;
    public $handle; // cURL resource handle.
    
    // Populated after execution:
    public $response; // Response body.
    public $headers; // Parsed reponse header object.
    public $info; // Response info object.
    public $error; // Response error string.
    
    public function __construct($options=array()){
        $this->options = array_merge(array(
            'headers' => array(), 
            'curl_options' => array(), 
            'user_agent' => "PHP RestClient/0.1", 
            'base_url' => NULL, 
            'format' => NULL, 
            'username' => NULL, 
            'password' => NULL
        ), $options);
    }
    
    public function set_option($key, $value){
        $this->options[$key] = $value;
    }
    
    public function get($url, $parameters=array(), $headers=array()){
        return $this->execute($url, 'GET', $parameters, $headers);
    }
    
    public function post($url, $parameters=array(), $headers=array()){
        return $this->execute($url, 'POST', $parameters, $headers);
    }
    
    public function put($url, $parameters=array(), $headers=array()){
        $parameters['_method'] = "PUT";
        return $this->post($url, $parameters, $headers);
    }
    
    public function delete($url, $parameters=array(), $headers=array()){
        $parameters['_method'] = "DELETE";
        return $this->post($url, $parameters, $headers);
    }
    
		/* Modified to support nested values */
    public function format_query($parameters, $primary='=', $secondary='&'){
        $query = "";
        foreach($parameters as $key => $value){
						if(is_array($value)){
							foreach($value as $k => $v) {
								$pair = array(urlencode($key.'['.$k.']'), urlencode($v));
								$query .= implode($primary, $pair) . $secondary;
							}
						} else {
							$pair = array(urlencode($key), urlencode($value));
							$query .= implode($primary, $pair) . $secondary;
						}
        }
        return rtrim($query, $secondary);
    }
    
    public function parse_response($response){
        $headers = array();
        $http_ver = strtok($response, "\n");
        
        while($line = strtok("\n")){
            if(strlen(trim($line)) == 0) break;
            
            list($key, $value) = explode(':', $line, 2);
            $key = trim(strtolower(str_replace('-', '_', $key)));
            $value = trim($value);
            if(empty($headers[$key])){
                $headers[$key] = $value;
            }
            elseif(is_array($headers[$key])){
                $headers[$key][] = $value;
            }
            else {
                $headers[$key] = array($headers[$key], $value);
            }
        }
        
        $this->headers = (object) $headers;
        $this->response = strtok("");
    }
    
    public function execute($url, $method='GET', $parameters=array(), $headers=array()){
        $client = clone $this;
        $client->url = $url;
        $client->handle = curl_init();
        $curlopt = array(
            CURLOPT_HEADER => TRUE, 
            CURLOPT_RETURNTRANSFER => TRUE, 
            CURLOPT_USERAGENT => $client->options['user_agent']
        );
        
        if($client->options['username'] && $client->options['password'])
            $curlopt[CURLOPT_USERPWD] = sprintf("%s:%s", 
                $client->options['username'], $client->options['password']);
        
        if(count($client->options['headers']) || count($headers)){
            $curlopt[CURLOPT_HTTPHEADER] = array();
            $headers = array_merge($client->options['headers'], $headers);
            foreach($headers as $key => $value){
                $curlopt[CURLOPT_HTTPHEADER][] = sprintf("%s:%s", $key, $value);
            }
        }
        
        if($client->options['format'])
            $client->url .= '.'.$client->options['format'];
        
        if(strtoupper($method) == 'POST'){
            $curlopt[CURLOPT_POST] = TRUE;
            $curlopt[CURLOPT_POSTFIELDS] = $client->format_query($parameters);
        }
        elseif(count($parameters)){
            $client->url .= strpos($client->url, '?')? '&' : '?';
            $client->url .= $client->format_query($parameters);
        }
        
        if($client->options['base_url']){
            if($client->url[0] != '/' || substr($client->options['base_url'], -1) != '/')
                $client->url = '/' . $client->url;
            $client->url = $client->options['base_url'] . $client->url;
        }
        $curlopt[CURLOPT_URL] = $client->url;
        
        if($client->options['curl_options']){
            // array_merge would reset our numeric keys.
            foreach($client->options['curl_options'] as $key => $value){
                $curlopt[$key] = $value;
            }
        }
        curl_setopt_array($client->handle, $curlopt);
        
        $client->parse_response(curl_exec($client->handle));
        $client->info = (object) curl_getinfo($client->handle);
        $client->error = curl_error($client->handle);
        
        curl_close($client->handle);
        return $client;
    }
}




class WireIOClient {
	const WIO_VERSION = '0.0.1';
	const API_VERSION = 'v1';
	const AUTH_VERSION = '1.0';
	
	private $public_key;
	private $private_key;
	private $base_uri;
	private $api_endpoint;
	private $action;
	private $rest_client;
	
	public function WireIOClient($public_key, $private_key) {
		$this->public_key = strtolower($public_key);
		$this->private_key = strtolower($private_key);
		$this->base_uri = 'https://app.getwire.io';
		$this->api_endpoint = 'api/' . self::API_VERSION . '/events';
		$this->action = 'fire';
		$this->rest_client = new RestClient(array(
			'format' => 'json',
			'base_url' => $this->base_uri . '/'
		));
	}
	
	public function on($event_name, $payload) {
		$auth_hash = array("auth_hash" => $this->generate_auth_hash_for($payload));
		$payload	 = array("payload" => $payload);
		$result = $this->rest_client->post($this->construct_endpoint_for($event_name),
			array_merge($auth_hash, $payload)
		);
		if($result->info->http_code == 200)
			return true;
		else
			return false;
	}
	
	private function construct_endpoint_for($event_name) {
		return $this->api_endpoint . '/' . $event_name . '/' . $this->action;
	}
	
	private function generate_auth_hash_for($payload) {
		$auth_hash = array(
			'auth_version' => self::AUTH_VERSION,
			'auth_key' => $this->public_key,
			'auth_timestamp' => time()
		);
	
		$params = array_change_key_case(array_merge($payload, $auth_hash));
		ksort($params);
		$encoded_params = http_build_query($params);
		$data = join("\n", array('POST', $this->api_endpoint, $encoded_params));
		$auth_hash['auth_signature'] = hash_hmac('sha256', $data, $this->private_key, false);
		return $auth_hash;
	}
}

?>