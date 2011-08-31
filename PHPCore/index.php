<?php
require 'lib/spyc.php5';
require 'lib/http.php';
require 'lib/JangleClasses.php';
require 'lib/Caches.php';
$config = Spyc::YAMLLoad('config/config.yml');

$url = parse_url(getenv('REQUEST_URI'));
$script_path = explode("/", $_SERVER["PHP_SELF"]);
array_pop($script_path);
$base_path = explode("/",str_replace(join($script_path, "/"),"",$url["path"]));
$path = array();
foreach($base_path as $p) {
    if($p) {
        array_push($path, $p);
    }
}

if($path[0] == "services") {

    if($_SERVER["REQUEST_METHOD"] != "GET") {
        header("HTTP/1.1 405 Method Not Allowed");
        exit();
    }
    if(count($config["services"]) > 0) {
        $services = new JangleServices();
        foreach($config["services"] as $service) {
            $http = new HttpGet($service["connector_url"]."services", $config["server"]["base_url"] . key($config["services"]));
            if($http->getStatus() != "200" && $http->getStatus() != "304" ) {
                header("HTTP/1.1 500");
                exit();                
            }
            $json = json_decode($http->getBody());
            if($json->type != "services") {
                header("HTTP/1.1 500");
                exit();
            }
            $service = new JangleService(key($config["services"]),$json);
            $services->addService($service);
        }
        header("Content-type: application/atomsvc+xml");
        echo($services->toXML());
        exit();
    } else {
        header("HTTP/1.1 500");
        exit();
    }    
} else {
    if($_SERVER["REQUEST_METHOD"] != "GET") {
        header("HTTP/1.1 405 Method Not Allowed");
        exit();
    }
    if(!in_array($path[0], array_keys($config["services"]))) {
        header("HTTP/1.1 404 Not Found");
        exit();        
    }
    if($_SERVER["REQUEST_METHOD"] == "GET") {
        $reqpath = array_slice($path, 1);
        $uri = $config["services"][$path[0]]["connector_url"].join($reqpath,"/");
        if(preg_match("/\/$/", $url["path"])) {
            $uri .= "/";
        }        
        if(count($_GET) > 0) {
            $uri .= "?".http_build_query($_GET);
        }
        $http = new HttpGet($uri, $config["server"]["base_url"] . $path[0]);
        if($_SERVER['PHP_AUTH_USER'] && $_SERVER['PHP_AUTH_PW']) {
            $http->setBasicAuth($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        }
        $http->doGet();
        $statusMap = array(400=>array("message"=>"Bad Request","body"=>"An illegal request was sent, most likely an invalid query parameter"),
            401=>array("message"=>"Unauthorized","body"=>"Authorization required to view this resource"),
            403=>array("message"=>"Forbidden", "body"=>"You have not been authorized to view this resource"),
            404=>array("message"=>"Not Found", "body"=>"The requested resource does not exist"),
            405=>array("message"=>"Method Not Allowed","body"=>"This resource does not permit GET requests"),
            410=>array("message"=>"Gone","body"=>"This resource no longer exists"));
        if($http->getStatus() != 200 && $http->getStatus() != 304) {
            header("HTTP/1.1 ".$http->getStatus()." ".$statusMap[$http->getStatus()]["message"]);
            if($auth = $http->getAuthHeaders()) {
                header("WWW-Authenticate:  ". $auth);
            }
            echo($statusMap[$http->getStatus()]["body"]);
            exit();
        }
        $json = json_decode($http->getBody());
        switch($json->type) {
            case("feed"):
            $response = new JangleFeed();
            header("Content-type: application/atom+xml");
            break;
            case("search"):
            $response = new JangleSearch();    
            header("Content-type: application/atom+xml");            
            $response->query = $_GET['query'];
            break;
            case("explain"):
            $response = new JangleExplain();
            header("Content-type: application/opensearchdescription+xml");
            break;
            case("services"):
            $response = new JangleServices();
            header("Content-type: application/atomsvc+xml");            
            break;
        }
        $response->fromConnectorResponse($json);
        echo($response->toXML());
    }    
    
}
?>