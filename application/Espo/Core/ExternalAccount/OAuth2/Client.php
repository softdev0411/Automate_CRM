<?php

namespace Espo\Core\ExternalAccount\OAuth2;

class Client
{
	const AUTH_TYPE_URI = 0;	
	const AUTH_TYPE_AUTHORIZATION_BASIC = 1;	
	const AUTH_TYPE_FORM = 2;	
	
	const ACCESS_TOKEN_TYPE_URI = 'Uri';	
	const ACCESS_TOKEN_TYPE_BEARER = 'Bearer';	
	const ACCESS_TOKEN_TYPE_OAUTH = 'OAuth';		
	
	const CONTENT_TYPE_APPLICATION = 0;	
	const CONTENT_TYPE_MULTIPART = 1;	
	
	const HTTP_METHOD_GET = 'GET';	
	const HTTP_METHOD_POST = 'POST';	
	const HTTP_METHOD_PUT = 'PUT';
	
	const HTTP_METHOD_DELETE = 'DELETE';	
	const HTTP_METHOD_HEAD = 'HEAD';	
	const HTTP_METHOD_PATCH = 'PATCH';	
	
	const GRANT_TYPE_AUTHORIZATION_CODE = 'authorization_code';	
	const GRANT_TYPE_REFRESH_TOKEN = 'refresh_token';	
	const GRANT_TYPE_PASSWORD = 'password';	
	const GRANT_TYPE_CLIENT_CREDENTIALS = 'client_credentials';	
	
	protected $clientId = null;
	
	protected $clientSecret = null;
	
	protected $accessToken = null;
	
	protected $authType = self::AUTH_TYPE_URI;
	
	protected $accessTokenType = self::ACCESS_TOKEN_TYPE_URI;	
	
	protected $accessTokenSecret = null;
	
	protected $accessTokenParamName = 'access_token';
	
	protected $certificateFile = null;
	
	protected $curlOptions = array();
	
	public function __construct(array $params = array())
    {
    	if (!extension_loaded('curl')) {
    		throw new \Exception('CURL extension not found.');
    	}
    }
    
    public function setClientId($clientId)
    {    
    	$this->clientId = $clientId;
	}
	
    public function setClientSecret($clientSecret)
    {    
    	$this->clientSecret = $clientSecret;
	}
    
    public function setAccessToken($accessToken)
    {
		$this->accessToken = $accessToken;
	}
	
	public function setAuthType($authType)
	{
		$this->authType = $authType;
	}
	
	public function setCertificateFile($certificateFile)
	{
		$this->certificateFile = $certificateFile;
	}
	
	public function setCurlOption($option, $value)
	{
		$this->curlOptions[$option] = $value;
	}
	
	public function setCurlOptions($options)
	{
		$this->curlOptions = array_merge($this->curlOptions, $options);
	}
	
	public function setAccessTokenType($accessTokenType)
	{
		$this->accessTokenType = $accessTokenType;
	}
	
	public function setAccessTokenSecret($accessTokenSecret)
	{
		$this->accessTokenSecret = $accessTokenSecret;
	}	
	
	public function fetch($url, $params = array(), $httpMethod = self::HTTP_METHOD_GET, array $httpHeaders = array(), $contentType = self::CONTENT_TYPE_MULTIPART)
	{
		if ($this->accessToken) {
			switch ($this->accessTokenType) {
				case self::ACCESS_TOKEN_TYPE_URI:
					$params[$this->accessTokenParamName] = $this->accessToken;
					break;
				case self::ACCESS_TOKEN_TYPE_BEARER:
					$httpHeaders['Authorization'] = 'Bearer ' . $this->accessToken;
					break;
				case self::ACCESS_TOKEN_TYPE_OAUTH:
					$httpHeaders['Authorization'] = 'OAuth ' . $this->accessToken;
					break;
				default:
					throw new \Exception('Unknown access token type.');
					
			}
		}
		return $this->execute($url, $params, $httpMethod, $httpHeaders, $contentType);
	}
	
	private function execute($url, $params = array(), $httpMethod, array $httpHeaders = array(), $contentType = self::CONTENT_TYPE_MULTIPART)
    {
    	$curlOptions = array(
    		CURLOPT_RETURNTRANSFER => true,
    		CURLOPT_SSL_VERIFYPEER => true,
    		CURLOPT_CUSTOMREQUEST => $httpMethod
    	);
    	    	
    	switch ($httpMethod) {
    		case self::HTTP_METHOD_POST:
    			$curlOptions[CURLOPT_POST] = true;
    		case self::HTTP_METHOD_PUT:
    		case self::HTTP_METHOD_PATCH:
				if (self::CONTENT_TYPE_APPLICATION === $contentType) {
					$postFields = http_build_query($params, null, '&');
				}
				$curlOptions[CURLOPT_POSTFIELDS] = $postFields;
				break;
			case self::HTTP_METHOD_HEAD:
				$curlOptions[CURLOPT_NOBODY] = true;
			case self::HTTP_METHOD_DELETE:
			case self::HTTP_METHOD_GET:
				$url .= '?' . http_build_query($parameters, null, '&');
				break;
			default:
				break;
    	}

		$curlOptions[CURLOPT_URL] = $url;
		
		$curlOptHttpHeader = array();
		foreach ($httpHeaders as $key => $value) {
			 $curlOptHttpHeader[] = "{$key}: {$parsed_urlvalue}";
		}
		$curlOptions[CURLOPT_HTTPHEADER] = $curlOptHttpHeader;
		
		$curlResource = curl_init();
		curl_setopt_array($curlResource, $curlOptions);
		
		if (!empty($this->certificateFile)) {
			curl_setopt($curlResource, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($curlResource, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curlResource, CURLOPT_CAINFO, $this->certificateFile);
		} else {
			curl_setopt($curlResource, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curlResource, CURLOPT_SSL_VERIFYHOST, 0);
		}
		
		if (!empty($this->curlOptions)) {
			curl_setopt_array($curlResource, $this->curlOptions);
		}
		
				
		$result = curl_exec($curlResource);
		$httpCode = curl_getinfo($curlResource, CURLINFO_HTTP_CODE);
		$contentType = curl_getinfo($curlResource, CURLINFO_CONTENT_TYPE);
		$resultArray = null;
		if ($curlError = curl_error($curlResource)) {
			throw new \Exception($curlError);
		} else {
			$resultArray = json_decode($result, true);
		}
		curl_close($curlResource);
		
		return array(
			'result' => (null !== $resultArray) ? $resultArray: $result,
			'code' => $httpCode,
			'contentType' => $contentType
		);
    }
    
    public function getAccessToken($url, $grantType, array $params)
    {
    	$params['grant_type'] = $grantType;
    	
    	$httpHeaders = array();
    	switch ($this->clientAuth) {
    		case self::AUTH_TYPE_URI:
    		case self::AUTH_TYPE_FORM:
    			$params['client_id'] = $this->clientId;
    			$params['client_secret'] = $this->clientSecret;
    			break;
    		case self::AUTH_TYPE_AUTHORIZATION_BASIC:
    			$params['client_id'] = $this->clientId;
    			$httpHeaders['Authorization'] = 'Basic ' . base64_encode($this->clientId .  ':' . $this->clientSecret);
    			break;
    		default:
    			throw new \Exception();
    	}
    	
    	return $this->execute($url, $params, self::HTTP_METHOD_POST, $httpHeaders, self::CONTENT_TYPE_APPLICATION);
    }
}

