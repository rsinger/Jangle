<?php
class JangleService {
    public $uri, $type, $title, $version;

    public $entities = array();

    public $categories = array();

    function __construct($uri){
        $this->uri = $uri;
        $this->type = "services";
        $this->version = "1.0";
    }    
    
    function addEntity($entity) {
        array_push($this->entities, $entity);
    }
    
    function addCategory($category) {
        array_push($this->categories, $category);
    }
    
    function setTitle($str) {
        $this->title = $str;
    }
    
    function out() {
        $hash = array("request"=>$this->uri, "type"=>$this->type, "version"=>$this->version);
        if(isset($this->title)) {
            $hash["title"] = $this->title;
        }
        if(count($this->entities) > 0) {
            $hash["entities"] = array();
            for($i = 0; $i < count($this->entities); $i++) {
                $h = $this->entities[$i]->toHash();
                $hash["entities"][key($h)] = $h[key($h)];
            }
        }
        if(count($this->categories) > 0) {
            $hash["categories"] = array();
            for($i = 0; $i < count($this->categories); $i++) {
                $h = $this->categories[$i]->toHash();
                $hash["categories"][key($h)] = $h[key($h)];
            }            
        }
        return(json_encode($hash));
    }
}

class JangleServiceEntity {
    public $type, $title, $path, $searchable, $searchDocument;
    public $categories = array();
    
    function __construct($type) {
        $this->type = $type;
        $this->searchable = false;
    }
    
    function setTitle($str) {
        $this->title = $str;
    }
    
    function setPath($path) {
        $this->path = $path;
    }
    function setSearchDocument($uri) {
        $this->searchable = true;
        $this->searchDocument = $uri;
    }
    
    function addCategory($term) {
        array_push($this->categories,$term);
    }
    
    function toHash() {
        $hash = array($this->type=>array("title"=>$this->title,"path"=>$this->path));
        if($this->searchable) {
            $hash[$this->type]["searchable"] = $this->searchDocument;
        } else {
            $hash[$this->type]["searchable"] = false;
        }
        if(count($this->categories) > 0) {
            $hash[$this->type]["categories"] = $this->categories;
        }
        return $hash;
    }
}

class JangleServiceCategory {
    public $term, $label, $scheme;
    function __construct($term) {
        $this->term = $term;
    }
    
    function setLabel($str) {
        $this->label = $str;
    }
    
    function setScheme($uri) {
        $this->scheme = $uri;
    }
    
    function toHash(){
        $hash = array($this->term=>array());
        if(isset($this->scheme)) {
            $hash[$this->term]["scheme"] = $this->scheme;
        }
        if(isset($this->label)) {
            $hash[$this->term]["label"] = $this->label;
        }        
        return $hash;
    }
}

class JangleFeed {
    public $uri, $type, $time, $offset, $totalResults;
    public $extensions = array();
    public $alternateFormats = array();
    public $stylesheets = array();
    public $categories = array();
    public $dataItems = array();
    public $formats = array();
    function __construct($uri){
        $this->uri = $uri;
        $this->type = "feed";
        $this->time = time();
        $this->offset = 0;
        $url = parse_url($uri);
        if($url["query"]) {
            parse_str($url["query"]);
            if(isset($offset)){
                $this->offset = $offset;
                settype($this->offset, "int");
            }
        }
    }

    function setOffset($int) {
        $this->offset = $int;
    }
    
    function setTotalResults($int) {
        $this->totalResults = $int;
    }
    
    function addAlternateFormat($formatUri, $uriToFormat) {
        $this->alternateFormats[$formatUri] = $uriToFormat;
    }
    
    function addDataItem($dataItem) {
        array_push($this->dataItems, $dataItem);
    }
    
    function addStyleSheet($uri) {
        array_push($this->stylesheets, $uri);
    }
    
    function addFormat($formatUri) {
        array_push($this->formats, $formatUri);
    }  
    
    function addCategory($category) {
        array_push($this->categories, $category);
    }
        
    function out() {
        $hash = array("request"=>$this->uri, "type"=>$this->type, "time"=>strftime("%Y-%m-%dT%H:%M:%S-%Z",$this->time),
        "offset"=>$this->offset);
        if(count($this->alternateFormats) > 0) {
            $hash["alternate_formats"] = $this->alternateFormats;
        }
        
        if(isset($this->totalResults)) {
            $hash["totalResults"] = $this->totalResults;
        } else {
            $hash["totalResults"] = 0;
        }

        if(count($this->extensions) > 0) {
            $hash["extensions"] = $this->extensions;
        }
        
        if(count($this->stylesheets) > 0) {
            $hash["stylesheets"] = $this->stylesheets;
        }
        
        if(count($this->categories) > 0) {
            $hash["categories"] = $this->categories;
        }
        if(count($this->formats) > 0) {
            $hash["formats"] = $this->formats;
        }        
        $data = array();
        foreach($this->dataItems as $item) {
            array_push($data, $item->toHash());
        }
        
        $hash["data"] = $data;

        return json_encode($hash);
    }    
}

class JangleSearch extends JangleFeed {
    function __construct($uri) {
        parent::__construct($uri);
        $this->type = "search";
    }
}

class JangleDataItem {
    public $title, $content, $contentType, $description, $created, $stylesheet, $author, $id, $updated, $format, $recordType;
    public $links = array();
    public $alternateFormats = array();
    public $categories = array();
    public $relationships = array();
    
    function __construct($id, $updated = NULL) {
        $this->id = $id;
        if($updated == NULL) {
            $this->updated = time();
        } else {        
            $this->updated = strtotime($updated);
        }
    }
    
    function setTitle($str) {
        $this->title = $str;
    }
    
    function setContentType($mime) {
        $this->contentType = $mime;
    }
    
    function setContent($content) {
        $this->content = $content;
    }
    
    function setCreated($date) {
        if(is_int($date)) {
            $this->created = $date;
        } else {
            $this->created = strtotime($date);
        }
    }
    
    function setDescription($string) {
        $this->description = $string;
    }
    
    function addRelationship($type, $uri=NULL) {
        if($uri != NULL) {
            $this->relationships["http://jangle.org/vocab/Entities#".ucfirst($type)] = $uri;
        } else {
            $this->relationships["http://jangle.org/vocab/Entities#".ucfirst($type)] = $this->id."/".$type."s";
        }
    }
    
    function addCategory($name) {
        array_push($this->categories,$name);
    }
    
    function setStylesheet($uri) {
        $this->stylesheet = $uri;
    }
    
    function setAuthor($str) {
        $this->author = $str;
    }
    
    function addAlternateFormat($formatUri,$uri) {
        $this->alternateFormats[$formatUri] = $uri;
    }
    
    function addLink($rel,$href,$type,$title=null) {
        if(!array_search($rel,$this->links)) {
            $this->links[$rel] = array();
        }
        array_push($this->links[$rel], array("href"=>$href,"type"=>$type,"title"=>$title));
    }
    
    function setFormat($formatUri) {
        $this->format = $formatUri;
    }
    
    function baseUri() {
        $uri = parse_url($this->id);
        $baseUri = '';
        if($uri["scheme"]) {
            $baseUri .= $uri["scheme"]."://";
        }
        if($uri["host"]) {
            $baseUri .= $uri["host"];
        }
        if($uri["port"]) {
            $baseUri .= ":".$uri["port"];
        }
        $baseUri .= $uri["path"];
        return $baseUri;        
    }
    
    function toHash() {
        $hash = array("id"=>$this->id, "updated"=>strftime("%Y-%m-%dT%H:%M:%S-%Z",$this->updated));
        if(isset($this->title)) {
            $hash["title"] = $this->title;
        }
        if(isset($this->contentType)) {
            $hash["content_type"] = $this->contentType;
        }  
        if(isset($this->content)) {
            $hash["content"] = $this->content;
        }  
        if(isset($this->format)) {
            $hash["format"] = $this->format;
        }      
        if(isset($this->created)) {
            $hash["created"] = strftime("%Y-%m-%dT%H:%M:%S-%Z",$this->created);
        }
        if(isset($this->author)) {
            $hash["author"] = $this->author;
        }
        if(isset($this->stylesheet)) {
            $hash["stylesheet"] = $this->stylesheet;
        }
        if(isset($this->description)) {
            $hash["description"] = $this->description;
        }        
        if(count($this->relationships) > 0) {
            $hash["relationships"] = $this->relationships;
        }
        if(count($this->categories) > 0) {
            $hash["categories"] = $this->categories;
        }
        if(count($this->alternateFormats) > 0) {
            $hash["alternate_formats"] = $this->alternateFormats;
        }
        if(count(array_keys($this->links)) > 0) {
            $hash['links'] = $this->links;
        }
        return $hash;
    }
    
}

class JangleExplain {
    public $shortName, $longName, $description, $exampleQuery, $developer, $contact, $attribution,
        $syndicationRight, $adultContent, $language, $template, $uri, $type;
    public $tags = array();
    public $inputEncoding = array();
    public $outputEncoding = array();
    public $image = array();
    public $indexes = array();
    function __construct($uri) {
        $this->uri = $uri;
        $this->type = "explain";
    }
    function setShortName($str) {
        $this->shortName = $str;
    }
    
    function setLongName($str) {
        $this->longName = $str;
    }
    
    function setDescription($str) {
        $this->description = $str;
    }
    
    function setTemplate($uri) {
        $this->template = $uri;
    }
    function setContact($str) {
        $this->contact = $str;
    }
    
    function addTag($tag) {
        array_push($this->tags, $tag);
    }
    
    function setImageLocation($uri) {
        $this->image["location"] = $uri;
    }
    
    function setImageHeight($pixels) {
        $this->image["height"] = $pixels;
    }
    
    function setImageWidth($pixels) {
        $this->image["width"] = $pixels;
    }
            
    function setImageType($mimeType) {
        $this->image["type"] = $mimeType;
    }  
    
    function setExampleQuery($str) {
        $this->exampleQuery = $str;
    }
    
    function setSyndicationRight($str) {
        $this->syndicationRight = $str;
    }
    
    function setDeveloper($str) {
        $this->developer = $str;
    }
    
    function setAttribution($str) {
        $this->attribution = $str;
    }
    
    function setAdultContent($bool) {
        $this->adultContent($bool);
    }
    
    function setLanguage($langCode) {
        $this->language = $langCode;
    }
    
    function addInputEncoding($str) {
        array_push($inputEncoding, $str);
    }
    
    function addOutputEncoding($str) {
        array_push($outputEncoding, $str);
    }
    
    function addContextSet($contextSet) {
        array_push($this->indexes, $contextSet);
    }
    
    function out() {
        $hash = array("request"=>$this->uri, "type"=>$this->type);
        if(isset($this->shortName)) {
            $hash["shortname"] = $this->shortName;
        }
        if(isset($this->longName)) {
            $hash["longname"] = $this->longName;
        }
        if(isset($this->description)) {
            $hash["description"] = $this->description;
        }
        if(isset($this->template)) {
            $hash["template"] = $this->template;
        }
        if(isset($this->contact)) {
            $hash["contact"] = $this->contact;
        }
        if(count($this->tags) > 0) {
            $hash["tags"] = $this->tags;
        }
        if(count($this->image) > 0) {
            $hash["image"] = $this->image;
        }
        if(isset($this->syndicationRight)) {
            $hash["syndicationright"] = $this->syndicationRight;
        }
        if(isset($this->developer)) {
            $hash["developer"] = $this->developer;
        }
        if(isset($this->attribution)) {
            $hash["attribution"] = $this->attribution;
        }
        if(isset($this->adultContent)) {
            $hash["adultcontent"] = $this->adultContent;
        }
        if(isset($this->language)) {
            $hash["language"] = $this->language;
        }
        if(count($this->inputEncoding) > 0) {
            $hash["inputencoding"] = $this->inputEncoding;
        }
        if(count($this->outputEncoding) > 0) {
            $hash["outputencoding"] = $this->outputEncoding;
        }        
        if(count($this->indexes) > 0 || isset($this->exampleQuery)) {
            $hash["query"] = array();
            if(isset($this->exampleQuery)) {
                $hash["query"]["example"] = $this->exampleQuery;
            }
            if(count($this->indexes) > 0) {
                $hash["query"]["context-sets"] = array();
                foreach($this->indexes as $contextSet) {
                    array_push($hash["query"]["context-sets"],$contextSet->toHash());
                }
            }
        }
        return(json_encode($hash));
    }
}

class JangleContextSet {
    public $name, $identifier;
    public $indexes = array();
    function __construct($name, $identifier=NULL) {
        $this->name = $name;
        $this->identifier = $identifier;
    }
    function addIndex($index) {
        array_push($this->indexes, $index);
    }
    
    function toHash() {
        $hash = array("name"=>$this->name, "identifier"=>$this->identifier);
        if(count($this->indexes) > 0) {
            $hash["indexes"] = $this->indexes;
        }
        return $hash;
    }
    
}
?>