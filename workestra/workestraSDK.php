<?php

/*

Copyright (c) 2014, Workestra LLC
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of Workestra nor the names of its contributors may be used
  to endorse or promote products derived from this software without specific
  prior written permission.


THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/
namespace Workestra\WorkestraSDK;

class WorkestraHTTPRequest {
	public $method="GET";
	public $headers=array();
	public $url;
	public $content=""; // Use raw text for most requests, or arrays for multipart/form-data
	public $multiPart=false;
}

class WorkestraHTTPResponse {
	public $statusCode;
	public $headers=array();
	public $content;

	/**
	 * If HTTP response indicates error, return true.
	 * @return bool
	 */
	public function isError() {
		return $this->statusCode < 200 || $this->statusCode > 299;
	}



	/**
	 * If the request is a success and is json, return an object from the response content.
	 *
	 * @return stdclass|null
	 */
	public function getContentJSON() {
		if(!$this->isError()) {
			return json_decode($this->content);
		}
		return null;
	}

	/**
	 * Return an object representing the content of the response. Mostly just a shortcut to {@see getContentJSON()} and {@see getContentXML()}
	 *
	 * @return \SimpleXMLElement|stdclass|null
	 */
	public function getContent() {
		if(!$this->isError() && strpos($this->headers['Content-Type'], 'application/json') !== false) {
			return $this->getContentJSON();
		}
		if(!$this->isError() && strpos($this->headers['Content-Type'], 'text/xml') !== false) {
			return $this->getContentXML();
		}
		return $this->content;
	}

	/**
	 * If the API returns an error, it may also send a message to describe the
	 * error. This message is only suitable for debugging purposes, not for
	 * displaying errors to the user. While we will endeavor to avoid changing these messages,
	 * you should be prepared for them to change. (in other words, avoid comparing
	 * the content of the error message to determine behavior).
	 *
	 * @return string
	 */
	public function getErrorMessage() {
		if($this->isError())
			return isset($this->headers['X-Workestra-Error-Messsage'])
				? $this->headers['X-Workestra-Error-Messsage']
				: $this->getContent();
		return null;
	}
}

interface WorkestraHTTP {
	function setBasicAuth($username, $password);
	function sendRequest(WorkestraHTTPRequest $request);
}


class WorkestraCurlHTTP implements WorkestraHTTP {
	private $basicAuthUsername;
	private $basicAuthPassword;

	/**
	 * Set the username and password used in HTTP basic Authentication.
	 * @param string $username
	 * @param string $password
	 */
	function setBasicAuth($username, $password) {
		$this->basicAuthUsername=$username;
		$this->basicAuthPassword=$password;
	}

	/**
	 * Given a string with HTTP headers, return an array like array('Header' => 'Value')
	 *
	 * @param string $headerString
	 * @return array
	 */
	function parseHeaders( $headerString ) {
		$retVal = array();
		$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headerString ));
		foreach( $fields as $field ) {
			if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
				$retVal[$match[1]] = trim($match[2]);
			}
		}
		return $retVal;
	}

	/**
	 * Perform the request described by the WorkestraHTTPRequest. Return a
	 * WorkestraHTTPResponse describing the response.
	 *
	 * @param \Workestra\WorkestraSDK\WorkestraHTTPRequest $request
	 * @return \Workestra\WorkestraSDK\WorkestraHTTPResponse
	 */
	function sendRequest(WorkestraHTTPRequest $request) {
		$response=new WorkestraHTTPResponse();

		$http=curl_init();
		curl_setopt($http, CURLOPT_URL, $request->url );
		curl_setopt($http, CURLOPT_CUSTOMREQUEST, $request->method );
		curl_setopt($http, CURLOPT_HTTPHEADER, $request->headers );
		curl_setopt($http, CURLOPT_POSTFIELDS, $request->content);

		curl_setopt($http, CURLOPT_HEADER, true );
		curl_setopt($http, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($http, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, 1);
		
		curl_setopt($http, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt($http, CURLOPT_USERPWD, $this->basicAuthUsername.':'.$this->basicAuthPassword);

		$response->content=curl_exec($http);


		if($response->content!==false) {
			$response->statusCode = curl_getinfo($http, CURLINFO_HTTP_CODE);
			$headerSize = curl_getinfo($http,CURLINFO_HEADER_SIZE);
			$response->headers= $this->parseHeaders( substr($response->content, 0, $headerSize) );
			$response->content= substr($response->content, $headerSize );
		} else {
			$response->statusCode=0;
			$response->content="Connection error";
		}
		return $response;
	}
}


class WorkestraSDK {
	protected $httpHandler;
	protected $baseUrl="https://www.workestra.co/api/v1";

	/**
	 *
	 *
	 * @param WorkestraHTTP $http
	 * @param string $baseUrl
	 */
	function __construct(WorkestraHTTP $http=null, $baseUrl=null) {
		if($http) {
			$this->httpHandler=$http;
		} else {
			$this->httpHandler=new WorkestraCurlHTTP();
		}
		if($baseUrl) {
			$this->baseUrl=$baseUrl;
		}

	}

	/**
	 * Set the api key to use.
	 *
	 * @param string $key
	 */
	function setApiKey($key){
		$this->httpHandler->setBasicAuth($key, 'w');
	}


	/**
	 * Set the basic authentication to use.
	 *
	 * @param string $username, $password
	 */

	function setBasicAuth($username, $password) {
		$this->httpHandler->setBasicAuth($username, $password);
	}

	/**
	 * Use the Login API to get a key for use in later API requests. 
	 * To set the ApiKey, you will need to parse the
	 * {@see \Workestra\WorkestraSDK\WorkestraHTTPResponse::$content} returned from this function and call
	 * {@see ApiKey()}
	 * 
	 * 
	 * @param string $email
	 * @param string $password
	 * @return \Workestra\WorkestraSDK\WorkestraHTTPResponse
	 */
	function requestApiKey($email, $password) {
		$request=new WorkestraHTTPRequest();
		$request->method="POST";
		$request->url=$this->baseUrl."/authenticate";
		$request->content="&email=".urlencode($email)."&password=".urlencode($password);
		return $this->httpHandler->sendRequest( $request );
	}

	/**
	 * Use the Login API to get a key for use in later API requests. Also use the
	 * response to set up the authentication for future requests.
	 *
	 * @param string $email
	 * @param string $password
	 * @return bool
	 */
	function login($email, $password) {
		$response = $this->requestApiKey($email, $password);
		if($response->isError()) {
			return false;
		}
		
		$json = $response->getContentJSON();


		$this->setApiKey($json->key);
		return true;
	}



	/**
	 *
	 * @return \Workestra\WorkestraSDK\WorkestraHTTPResponse
	 */
	function listNotifications() {
		$request=new WorkestraHTTPRequest();
		$request->method="GET";
		$request->url=$this->baseUrl."/notifications";
		return $this->httpHandler->sendRequest( $request );
	}




}



?>
