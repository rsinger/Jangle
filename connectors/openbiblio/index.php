<?php

require 'lib/jangle.php';
require 'lib/spyc.php5';
require 'lib/models.php';

$config = Spyc::YAMLLoad('config/config.yml');
$config["base_uri"] = getenv('HTTP_X_CONNECTOR_BASE');
$url = parse_url($_SERVER['REQUEST_URI']);

$base_path = explode("/",str_replace($config["global_options"]["path_to_connector"],"",$url["path"]));
$path = array();
foreach($base_path as $p) {
    if($p) {
        array_push($path, $p);
    }
}
$request_uri = $config["base_uri"]."/".join($path,"/");
if(preg_match("/\/$/", $url["path"])) {
    $request_uri .= "/";
}
if(count($_GET) > 0) {
    $request_uri .= "?".http_build_query($_GET);
}
$explain = false;
$search = false;
$_REQUEST['resource'] = $path[0];
if($path[1]) {
    if($path[1] == "-") {
        $_REQUEST["category"] = $path[2];
    } elseif($path[1] == "search") {
        if($path[2] == "description") {
            $explain = true;
        } else {
            $search = true;
        }
    } else {
        $_REQUEST["id"] = $path[1];
        if($path[2]) {
            $_REQUEST["relation"] = $path[2];
        }
        if($path[3]) {
            $_REQUEST["category"] = $path[4];
        }
    }
}

checkRequestSanity($_REQUEST, $explain, $search, $config);

if(getenv('HTTP_ACCEPT') == "application/json") {
    header("Content-type: application/json");
} elseif (getenv('HTTP_ACCEPT') == "text/json") {
    header("Content-type: text/json");
} else {
    header("Content-type: text/plain");
}


if($_REQUEST['resource'] == "services") {
    $j = new JangleService($request_uri);
    cacheCheck($j);
    buildServiceResponseFromConfig($j,$config);
} elseif ($explain) {
    $j = new JangleExplain($request_uri);    
    buildExplainResponseFromConfig($j,$config);
} else {

    switch($_REQUEST['resource']){
        case 'actors':
        $resources = new Actors($config);
        break;
        case 'collections':
        $resources = new Collections($config);
        break;
        case 'items':
        $resources = new Items($config);
        break;
        case 'resources':
        $resources = new Resources($config); 
        break;
    }

    if($_REQUEST['offset']) {
        $offset = $_REQUEST['offset'];
    } else {
        $offset = 0;
    }
    if($_REQUEST['format']) {
        $format = $_REQUEST['format'];
    } else {
        if($_REQUEST['relation']) {
            $format = $config["entities"][$_REQUEST['relation']]["record_types"][0];
        } else {
            $format = $config["entities"][$_REQUEST['resource']]["record_types"][0];
        }
    }
    if($search) {
        $j = new JangleSearch($request_uri);
    } else {
        $j = new JangleFeed($request_uri);
    }
    if($_REQUEST["id"]) {
        if($_REQUEST["relation"]) {
            if($_REQUEST["category"]) {
                $entries = $resources->relationFindByFilter($_REQUEST["id"],$_REQUEST["relation"],$_REQUEST["category"],$offset,$format);
                $j->setTotalResults($resources->relationFilterCount($_REQUEST["id"],$_REQUEST["relation"],$_REQUEST["category"]));
                $j->addCategory($_REQUEST['category']);
            } else {
                $entries = $resources->relationFind($_REQUEST["id"], $_REQUEST["relation"], $offset, $format);
                if(count($entries) == 0) {
                    header("HTTP/1.1 404 Not Found");
                    exit();                    
                }                
                $j->setTotalResults($resources->relationCount($_REQUEST["id"], $_REQUEST["relation"]));
            }
            addAlternateFormatsToFeed($j,$format,$config["entities"][$_REQUEST["relation"]]["record_types"],$config["record_types"]);                        
        } else {
            $entries = $resources->find($_REQUEST["id"], $offset, $format);
            if(count($entries) == 0) {
                header("HTTP/1.1 404 Not Found");
                exit();                    
            }            
            $j->setTotalResults($resources->count($_REQUEST["id"]));          
            addAlternateFormatsToFeed($j,$format,$config["entities"][$_REQUEST["resource"]]["record_types"],$config["record_types"]);            
        }
    } else {        
        if($_REQUEST["category"]) {
            $entries = $resources->findByFilter($_REQUEST["category"],$offset,$format);         
            $j->setTotalResults($resources->filterCount($_REQUEST["category"]));   
            $j->addCategory($_REQUEST['category']);
        } elseif($search) {
            $j->setTotalResults($resources->searchCount($_REQUEST["query"]));
            if($j->totalResults > 0) {
                $limit = NULL;
                if($_REQUEST["limit"]) {
                    $limit = $_REQUEST["limit"];
                }
                $entries = $resources->search($_REQUEST["query"],$offset, $format, $limit);
            } else {
                $entries = array();
            }
        } else {
            $entries = $resources->all($offset, $format);
            cacheCheck($j);
            $j->setTotalResults($resources->count());
        }
        addAlternateFormatsToFeed($j,$format,$config["entities"][$_REQUEST["resource"]]["record_types"],$config["record_types"]);
    }
    for($i = 0; $i < count($entries); $i++) {
        $j->addDataItem($entries[$i]);
    }

    if($config["record_types"][$format]["stylesheets"]) {
        if($config["record_types"][$format]["stylesheets"]["feed"]) {
            if(array_search($resources->entity(), $config["record_types"][$format]["stylesheets"]["feed"]["entities"]) !== False) {
                $j->addStylesheet($config["record_types"][$format]["stylesheets"]["feed"]["uri"]);
            }
        }        
    }
    for($i = 0; $i < count($j->dataItems); $i++) {
        if(!in_array($j->dataItems[$i]->format,$j->formats)) {
            $j->addFormat($j->dataItems[$i]->format);
        }
    }

}
echo $j->out();

function buildServiceResponseFromConfig(&$response,$config) {
    $entityMap = array("actors"=>"Actor","resources"=>"Resource","items"=>"Item","collections"=>"Collection");
    $response->setTitle($config["global_options"]["service_name"]);
    for($i = 0; $i < count($config["entities"]); $i++) {
        $entity = new JangleServiceEntity($entityMap[key($config["entities"])]);
        $entity->setTitle($config["entities"][key($config["entities"])]["title"]);
        $entity->setPath($config["global_options"]["path_to_connector"]."/".key($config["entities"]));      
        for($x = 0; $x < count($config["entities"][key($config["entities"])]["categories"]); $x++) {
            $entity->addCategory($config["entities"][key($config["entities"])]["categories"][$x]);
        }
        if($config["entities"][key($config["entities"])]["search"]) {
            $entity->setSearchDocument($config["global_options"]["path_to_connector"]."/".key($config["entities"])."/search/description/");
        }
        $response->addEntity($entity);
        next($config["entities"]);
    }
    
    for($i = 0; $i < count($config["categories"]); $i++) {
        $category = new JangleServiceCategory(key($config["categories"]));
        $category->setScheme($config["categories"][key($config["categories"])]["scheme"]);
        $response->addCategory($category);
    }
}

function buildExplainResponseFromConfig(&$response,$config) {
    $c = $config["entities"][$_REQUEST["resource"]]["search"];
    $response->setShortName($config["entities"][$_REQUEST["resource"]]["title"]);
    if($c["longname"]) {
        $response->setLongName($c["longname"]);
    }
    if($c["description"]) {
        $response->setDescription($c["description"]);
    }    
    if($c["contact"]) {
        $response->setContact($c["contact"]);
    }        
    if($c["tags"]) {
        for($i = 0; $i < count($c["tags"]); $i++) {
            $response->addTag($c["tags"][$i]);
        }
    } 
    $opensearch_params = array("searchTerms","count","startIndex","startPage","language","inputEncoding","outputEncoding");
    $url = $config['base_uri'];
    if(!ereg("/\/$/",$url)) {
        $url .= "/";
    }
    $url .= $_REQUEST['resource']."/search?";    
    if($c["parameters"]) {
        $query_args = array();
        foreach(array_keys($c["parameters"]) as $key) {
            $query_arg = $key."=";
            if(array_search(ereg_replace("\?$",'',$c["parameters"][$key]),$opensearch_params) !== False) {
                $query_arg .= "{".$c["parameters"][$key]."}";
            } else {
                $query_arg .= "{jangle:".$c["parameters"][$key]."}";
            }
            array_push($query_args, $query_arg);
        }
        $url .= join($query_args,"&");
    } else {
        $url .= "query={searchTerms}";
    }
    $response->setTemplate($url);
    if($c["image"]) {
        $response->setImageLocation($c["image"]["location"]);
        if($c["image"]["height"]) {
            $response->setImageHeight($c["image"]["height"]);            
        }
        if($c["image"]["width"]) {
            $response->setImageWidth($c["image"]["width"]);            
        }
        if($c["image"]["type"]) {
            $response->setImageType($c["image"]["type"]);            
        }  
    }
    if($c["query"]) {
        $response->setExampleQuery($c["query"]);
    }
    
    if($c["developer"]) {
        $response->setDeveloper($c["developer"]);
    }
    
    if($c["attribution"]) {
        $response->setAttribution($c["attribution"]);
    }
    
    if($c["syndicationright"]) {
        $response->setSyndicationRight($c["syndicationright"]);
    }
    
    if($c["adultcontent"]) {
        $response->setAdultContent($c["adultcontent"]);
    }
    
    if($c["language"]) {
        $response->setLanguage($c["language"]);
    }
    
    if($c["inputencoding"]) {
        $response->addInputEncoding($c["inputencoding"]);
    }
    
    if($c["outencoding"]) {
        $response->addInputEncoding($c["outputencoding"]);
    }    
    $ctx_sets = array();
    for($i = 0; $i < count($c["indexes"]); $i++) {
        list($name, $index) = explode(".",$c["indexes"][$i]);
        if(!$ctx_sets[$name]) {
            $ctx_sets[$name] = array();
        }
        if(!in_array($index, $ctx_sets[$name])) {
            array_push($ctx_sets[$name],$index);
        }
    }
    if(count($ctx_sets) > 0) {
        foreach(array_keys($ctx_sets) as $ctx_set) {
            $ident = $config["context-sets"][$ctx_set]["identifier"];
            $ctx = new JangleContextSet($ctx_set, $ident);
            if(count($ctx_sets[$ctx_set]) > 0) {
                foreach($ctx_sets[$ctx_set] as $index) {
                    $ctx->addIndex($index);
                }                
            }
            $response->addContextSet($ctx);
        }        
    }
    return($response);
}

function checkRequestSanity($vars, $explain, $search, $config) {
    if(!$vars["resource"]) {
        header("HTTP/1.1 404 Not Found");
        exit();        
    }
    if($vars["resource"] == "services") {
        if($vars["relation"] || $vars["id"] || $vars["category"]) {
            header("HTTP/1.1 404 Not Found");
            exit();
        }
        return;
    }
    if(!in_array($vars["resource"], array_keys($config["entities"]))) {
        header("HTTP/1.1 404 Not Found");
        exit();        
    }
    
    if($vars["relation"]) {
        if(!in_array($vars["relation"], $config["entities"][$vars["resource"]]["services"])) {
            header("HTTP/1.1 404 Not Found");
            exit();            
        }
        if($vars["format"]) {
            if(!in_array($vars["format"], $config["entities"][$vars["relation"]]["record_types"])) {
                header("HTTP/1.1 400 Bad Request");
                exit();            
            }
        }        
        if($vars["category"]) {
            if(!in_array($vars["category"], $config["entities"][$vars["relation"]]["categories"])) {
                header("HTTP/1.1 404 Not Found");
                exit();                
            }
        }
        return;
    }
    if($vars["category"]) {
        if(!in_array($vars["category"], $config["entities"][$vars["resource"]]["categories"])) {
            header("HTTP/1.1 404 Not Found");
            exit();                
        }
    }   
    if($search || $explain) {
        if(!$config["entities"][$vars["resource"]]["search"]) {
            header("HTTP/1.1 404 Not Found");
            exit();            
        }
    }
    if($vars["format"]) {
        if(!in_array($vars["format"], $config["entities"][$vars["resource"]]["record_types"])) {
            header("HTTP/1.1 400 Bad Request");
            exit();            
        }
    }
}

function addAlternateFormatsToFeed(&$feed,$format,$resourceRecordTypes,$allRecordTypes) {
    if(count($resourceRecordTypes)) {
        foreach($resourceRecordTypes as $type) {
            if($type != $format) {
                $u = parse_url($feed->uri);
                if($u['query']) {
                    parse_str($u['query'], $q);
                    $q['format'] = $type;
                    $uri = $u['scheme']."://".$u['host'];
                    if($u['port']) {
                        $uri .= ":".$u['port'];
                    }
                    $uri .= $u['path']."?".http_build_query($q);
                } else {
                    $uri = $feed->uri."?format=".$type;
                }
                $feed->addAlternateFormat($allRecordTypes[$type]["uri"],$uri);
            }            
        }
    }
}

function cacheCheck(&$obj) {
    if(!method_exists($obj, "cache")) {
        return;
    }
    
}
?>