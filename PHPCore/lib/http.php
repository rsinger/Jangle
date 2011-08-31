<?php
require 'HTTP/Request.php';

class HttpGet {
    protected $status, $message, $body, $uri, $client, $responseHeaders;
    protected $requestHeaders = array();
    function __construct($uri, $connector_base) {
        $this->uri = $uri;
        $this->headers["X-Connector-Base"] = $connector_base;
        $this->headers["accept"] = "application/json";
        //$this->client = new HTTP_Client(NULL,$this->headers);     
        $this->client = new HTTP_Request($this->uri);
        foreach($this->headers as $key => $val) {
            $this->client->addHeader($key, $val);
        }
        //$this->doGet();
    }
    
    function setBasicAuth($username, $password) {
        $this->client->setBasicAuth($username, $password);
    }
    function doGet() {
        $this->client->setMethod(HTTP_REQUEST_METHOD_GET);
        $response = $this->client->sendRequest($this->uri);
        $this->status = $this->client->getResponseCode();
        $this->responseHeaders = $this->client->getResponseHeader();
        $this->body = $this->client->getResponseBody();
    }
    
    function getBody() {
        return $this->body;
    }
    
    function getStatus() {
        return $this->status;
    }
    
    function getAuthHeaders() {
        if ($this->responseHeaders['www-authenticate']) {
            return $this->responseHeaders['www-authenticate'];
        }
    }
}
?>