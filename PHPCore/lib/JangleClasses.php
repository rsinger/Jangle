<?php

class JangleServices {
    protected $services = array();
    function addService($service) {
        array_push($this->services, $service);
    }
    
    function toXML() {
        $xml = new xmlWriter();
        $xml->openMemory();
        $xml->startDocument('1.0','UTF-8');
        $xml->startElement("service");
        $xml->writeAttribute("xmlns","http://www.w3.org/2007/app");
        $xml->writeAttribute("xmlns:atom","http://www.w3.org/2005/Atom");
        for($i = 0; $i < count($this->services); $i++) {
            $xml->startElement("workspace");
            $xml->writeElement("atom:title",$this->services[$i]->name);
            for($x = 0; $x < count($this->services[$i]->collections);$x++) {
                $xml->startElement("collection");
                $xml->writeAttribute("href",$this->services[$i]->collections[$x]->location);
                $xml->writeElement("atom:title", $this->services[$i]->collections[$x]->title);
                if(count($this->services[$i]->collections[$x]->categories) > 0) {
                    $xml->startElement("atom:categories");
                    $xml->writeAttribute("fixed","no");

                    for($y = 0; $y < count($this->services[$i]->collections[$x]->categories); $y++) {
                        $xml->startElement("atom:category");
                        $xml->writeAttribute("term", $this->services[$i]->collections[$x]->categories[$y]);
                        if($this->services[$i]->categories[$this->services[$i]->collections[$x]->categories[$y]]) {
                             $xml->writeAttribute("scheme",$this->services[$i]->categories[$this->services[$i]->collections[$x]->categories[$y]]["scheme"]);
                        }
                        $xml->endElement();
                    }
                    $xml->endElement();
                }
                
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
        return($xml->outputMemory(true));
    }
    
    function fromConnectorResponse($json) {
        $svc = new JangleService($json->title, $json);
        $this->addService($svc);
    }
}

class JangleService {
    public $name;
    public $collections = array();
    public $categories = array();    
    function __construct($name, $connectorResponse) {
        $this->name = $name;
        foreach($connectorResponse->entities as $entity) {
            $collection = new JangleServiceCollection();
            $collection->location = $entity->path;
            if($entity->title) {
                $collection->title = $entity->title;
            } else {
                $collection->title = key($connectorResponse->entities);                
            }
            for($i = 0; $i < count($entity->categories); $i++) {
                array_push($collection->categories, $entity->categories[$i]);
            }
            array_push($this->collections, $collection);
        }
        if(count($connectorResponse->categories > 0)) {
            foreach(get_object_vars($connectorResponse->categories) as $cat) {
                $this->addCategory(key($connectorResponse->categories), $cat->scheme);
            }
        }
    }
    
    function addCategory($term, $scheme) {
        $this->categories[$term] = array("scheme"=>$scheme);
    }    
}

class JangleServiceCollection {
    public $location, $title;
    public $categories = array();
}

class JangleFeed {
    protected $uri, $offset, $totalResults, $time;
    protected $data = array();
    private $formats = array();
    private $alternateFormats = array();
    private $extensions = array();
    private $stylesheets = array();
    
    function __construct() {
        $uri = 'http';
        if($_SERVER['HTTPS']) {
            $uri .= "s";
        }
        $uri .= "://".$_SERVER['SERVER_NAME'];
        if($_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') {
            $uri .= ":".$_SERVER['SERVER_PORT'];
        }
        $uri .= $_SERVER['REQUEST_URI'];
        $this->uri = $uri;
    }
    
    function fromConnectorResponse($json) {
        //$this->uri = $json->request;
        $this->offset = $json->offset;
        $this->totalResults = $json->totalResults;
        $this->time = $json->time;
        $this->formats = $json->formats;
        for($i = 0; $i < count($json->data); $i++) {
            $resource = new JangleResource();
            $resource->fromConnectorResponse($json->data[$i]);
            $this->addData($resource);
        }
        if($json->alternate_formats) {
            $this->alternateFormats = get_object_vars($json->alternate_formats);
        }
        if($json->stylesheets) {
            for($i = 0; $i < count($json->stylesheets); $i++) {
                array_push($this->stylesheets, $json->stylesheets[$i]);
            }
        }
    }
    
    function addData($resource) {
        array_push($this->data, $resource);
    }
    
    function pager() {
        $u = parse_url($this->uri);
        $pages = array();
        $query = array();
        if($u['query']) {
            parse_str($u['query'], $query);
        }
        if($this->offset > 0) {
            $this->array_delete($query, "offset");
            $pages['first'] = $this->strippedURL($u, $query);
            $query['offset'] = ($this->offset-count($this->data));
            if($query['offset'] < 0) {
                $query['offset'] = 0;
            }
            $pages['previous'] = $this->strippedURL($u, $query);
        }
     
        if(count($this->data)) {
            if($this->offset+(count($this->data)) < $this->totalResults) {
                $query['offset'] = $this->offset+(count($this->data));
                $pages['next'] = $this->strippedURL($u, $query);
                $query['offset'] = ($this->totalResults - ($this->totalResults % count($this->data)));
                $pages['last'] = $this->strippedURL($u, $query);
            }
        }

        return $pages;
    }
    
    function strippedURL($parsedURL,$getVars=NULL) {
        $u = $parsedURL['scheme']."://".$parsedURL['host'];
        if($parsedURL['port']) {
            $u .= ":".$parsedURL['port'];
        }
        if($parsedURL['path']) {
            $u .= $parsedURL['path'];
        }
        if($getVars) {
            $u .= "?".http_build_query($getVars);
        }        
        return $u;
    }
    
    function array_delete(&$ary,$key_to_be_deleted)
    {
        $new = array();
        if(is_string($key_to_be_deleted)) {
            if(!array_key_exists($key_to_be_deleted,$ary)) {
                return;
            }
            foreach($ary as $key => $value) {
                if($key != $key_to_be_deleted) {
                    $new[$key] = $value;
                }
            }
            $ary = $new;
        }
        if(is_array($key_to_be_deleted)) {
            foreach($key_to_be_deleted as $del) {
                array_delete(&$ary,$del);
            }
        }
    }    
    
    function buildXML() {
        $xml = new xmlWriter();
        $xml->openMemory();     
        $xml->startDocument('1.0','UTF-8');   
        $xml->startElement("feed");
        $xml->writeAttribute("xmlns","http://www.w3.org/2005/Atom");
        $xml->writeAttribute("xmlns:jangle", "http://jangle.org/vocab/");
        $xml->startElement("link");
        $xml->writeAttribute("href", $this->uri);
        $xml->writeAttribute("type","application/atom+xml");        
        $xml->endElement();
        $xml->writeElement("id", $this->uri);        
        $xml->writeElement("updated", $this->time);
        $xml->startElement("link");
        $xml->writeAttribute("rel","self");
        $xml->writeAttribute("href",$this->uri);
        $xml->writeAttribute("type","application/atom+xml");
        if(count($this->formats) == 1 && $this->formats[0] != NULL) {
            $xml->writeAttribute("jangle:format",$this->formats[0]);
        }
        $xml->endElement();        
        $pages = $this->pager();
        if(count($pages) > 0) {
            foreach(array_keys($pages) as $page) {
                $xml->startElement("link");
                $xml->writeAttribute("rel",$page);
                $xml->writeAttribute("href",$pages[$page]);
                $xml->writeAttribute("type","application/atom+xml");
                $xml->endElement();
            }
        }
        if(count($this->alternateFormats)) {
            foreach(array_keys($this->alternateFormats) as $alt) {
                $xml->startElement("link");
                $xml->writeAttribute("rel",$alt);
                $xml->writeAttribute("href",$this->alternateFormats[$alt]);
                $xml->writeAttribute("type","application/atom+xml");
                $xml->endElement();
            }
        }
        
        for($i = 0; $i < count($this->data); $i++) {
            $xml->startElement("entry");
            $xml->startElement("link");
            $xml->writeAttribute("href",$this->data[$i]->id);
            $xml->writeAttribute("jangle:format",$this->data[$i]->format);
            $xml->endElement();
            $xml->writeElement("id",$this->data[$i]->id);
            $xml->writeElement("title",$this->data[$i]->title);
            $xml->writeElement("updated",$this->data[$i]->updated);
            $xml->startElement("author");
            $xml->writeElement("name",$this->data[$i]->author);
            $xml->endElement();
            if($this->data[$i]->content) {
                $xml->startElement("content");
                $xml->writeAttribute("type",$this->data[$i]->contentType);
                if($this->data[$i]->stylesheet) {
                    $content = $this->transformer($this->data[$i]->content, $this->data[$i]->stylesheet);
                } else {
                    $content = $this->data[$i]->content;
                }
                if(preg_match("/xml/",$this->data[$i]->contentType)) {
                    $xml->writeRaw($content);
                } else {
                    $xml->text($content);
                }
                $xml->endElement();
            }
            if($this->data[$i]->description) {
                $xml->writeElement("summary",$this->data[$i]->description);
            }
            if($this->data[$i]->created) {
                $xml->writeElement("created",$this->data[$i]->created);
            }      
            if(count($this->data[$i]->alternateFormats)) {
                foreach(array_keys($this->data[$i]->alternateFormats) as $alt) {
                    $xml->startElement("link");
                    $xml->writeAttribute("rel",$alt);
                    $xml->writeAttribute("type","application/atom+xml");
                    $xml->writeAttribute("href", $this->data[$i]->alternateFormats[$alt]);
                    $xml->endElement();
                }
            }
            if(count($this->data[$i]->relationships)) {
                foreach(array_keys($this->data[$i]->relationships) as $relation) {
                    $xml->startElement("link");
                    $xml->writeAttribute("rel","related");
                    $xml->writeAttribute("type","application/atom+xml");
                    $xml->writeAttribute("href", $this->data[$i]->relationships[$relation]);
                    $xml->writeAttribute("jangle:relationship",$relation);
                    $xml->endElement();
                }
            }            
            if(count($this->data[$i]->categories)) {
                foreach($this->data[$i]->categories as $cat) {
                    $xml->startElement("category");
                    $xml->writeAttribute("term",$cat);
                    $xml->endElement();
                }
            }
            if(count($this->data[$i]->links)) {
                foreach($this->data[$i]->links as $link) {
                    $xml->startElement("link");
                    $xml->writeAttribute("rel",array_search($link, $this->data[$i]->links));
                    $xml->writeAttribute("href",$link[0]->href);
                    if($link["type"]) {
                        $xml->writeAttribute("type",$link[0]->type);
                    }
                    $xml->endElement();
                }
            }
            $xml->endElement();
        }
        $xml->endElement();
        return $xml;
    }
    
    function transformer($xml,$xsl) {
        $xmlDoc = DOMDocument::loadXML($xml);
        $xslDoc = DOMDocument::load($xsl);
        $proc = new XSLTProcessor();
        $proc->importStylesheet($xslDoc);                              
        return $proc->transformToXML($xmlDoc);         
    }
    
    function toXML() {
        $x = $this->buildXML();
        $xml = $x->outputMemory(true);     
        if(count($this->stylesheets)) {
            for($i = 0; $i < count($this->stylesheets); $i++) {
                $xml = $this->transformer($xml, $this->stylesheets[$i]);
            }
        }
        return $xml;
    }
}

class JangleResource {
    public $id, $title, $author, $content, $contentType, $format, $updated, $created, $description, $stylesheet;
    public $alternateFormats = array();
    public $relationships = array();
    public $categories = array();
    public $links = array();
    
    function fromConnectorResponse($json) {
        $this->id = $json->id;
        $this->title = $json->title;
        $this->updated = $json->updated;
        $this->format = $json->format;
        if($json->author) {
            $this->author = $json->author;         
        } else {
            $this->author = "n/a";
        }
        if($json->content) {
            $this->content = $json->content;
            $this->contentType = $json->content_type;
        }  
        if($json->created) {
            $this->created = $json->created;
        }        
        if($json->description) {
            $this->description = $json->description;
        }
        if($json->stylesheet) {
            $this->stylesheet = $json->stylesheet;
        }
        if($json->alternate_formats) {
            $this->alternateFormats = get_object_vars($json->alternate_formats);
        }
        if($json->relationships) {
            $this->relationships = get_object_vars($json->relationships);
        }
        if($json->categories) {
            $this->categories = $json->categories;
        }
        if($json->links) {
            $this->links = get_object_vars($json->links);
        }
    }
    
}

class JangleSearch extends JangleFeed {
    public $query;
    function toXML() {
        $xml = parent::toXML();
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        foreach($doc->getElementsByTagName("feed")  as $root) {
            $osNamespace = $doc->createAttribute("xmlns:opensearch");
            $root->appendChild($osNamespace);
            $osNamespaceValue = $doc->createTextNode("http://a9.com/-/spec/opensearch/1.1/");
            $osNamespace->appendChild($osNamespaceValue);
            $totalResults = $doc->createElement("opensearch:totalResults", $this->totalResults);
            $root->appendChild($totalResults);

            $startIndex = $doc->createElement("opensearch:startIndex", $this->offset);
            $root->appendChild($startIndex);

            $itemsPerPage = $doc->createElement("opensearch:itemsPerPage", count($this->data));
            $root->appendChild($itemsPerPage);
            $query = $doc->createElement("opensearch:Query");
            $root->appendChild($query);            
            $role = $doc->createAttribute("role");
            $query->appendChild($role);
            $role->appendChild($doc->createTextNode("request"));
            $terms = $doc->createAttribute("searchTerms");
            $query->appendChild($terms);
            $terms->appendChild($doc->createTextNode($this->query));            
            $si = $doc->createAttribute("startIndex");
            $query->appendChild($si);
            $si->appendChild($doc->createTextNode("0"));
        }
        return $doc->saveXML();
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
    function fromConnectorResponse($json) {
        $this->shortName = $json->shortname;
        if($json->longname) {
            $this->longName = $json->longname;
        }
        if($json->description) {
            $this->description = $json->description;
        }
        if($json->query) {
            $query = get_object_vars($json->query);
            if($json->query->example) {
                $this->exampleQuery = $query["example"];
            }

            if($query["context-sets"]) {
                foreach($query["context-sets"] as $set) {
                    $ctx = new JangleContextSet();
                    $ctx->fromConnectorResponse($set);
                    array_push($this->indexes, $ctx);
                }
            }
        }
        if($json->developer) {
            $this->developer = $json->developer;
        }
        if($json->contact) {
            $this->contact = $json->contact;
        }
        if($json->attribution) {
            $this->attribution = $json->attribution;
        }
        if($json->syndicationright) {
            $this->syndicationRight = $json->syndicationright;
        }
        if($json->adultcontent) {
            if($json->adultcontent == true) {
                $this->adultContent = "True";
            }
        }
        if($json->language) {
            $this->language = $json->language;
        }
        if($json->template) {
            $this->template = $json->template;
        }
        if($json->tags) {
            $this->tags = $json->tags;
        }
        if($json->inputencoding) {
            $this->inputEncoding = $json->inputencoding;
        }
        if($json->outputencoding) {
            $this->outputEncoding = $json->outputencoding;
        }  
        if($json->image) {
            $this->image = get_object_vars($json->image);
        }
    }
    
    function toXML() {
        $xml = new xmlWriter();
        $xml->openMemory();
        $xml->startDocument('1.0','UTF-8');
        $xml->startElement("OpenSearchDescription");
        $xml->writeAttribute("xmlns","http://a9.com/-/spec/opensearch/1.1/");
        $xml->writeAttribute("xmlns:jangle","http://jangle.org/opensearch/");
        $xml->writeElement("ShortName",$this->shortName);
        if(isset($this->longName)) {
            $xml->writeElement("LongName",$this->longName);
        }
        if(isset($this->description)) {
            $xml->writeElement("Description",$this->description);
        }
        if(isset($this->developer)) {
            $xml->writeElement("Developer",$this->description);
        }
        if(isset($this->contact)) {
            $xml->writeElement("Contact",$this->contact);
        }
        if(isset($this->attribution)) {
            $xml->writeELement("Attribution",$this->attribution);
        }
        if(isset($this->syndicationRight)) {
            $xml->writeElement("SyndicationRight",$this->syndicationRight);
        }
        if(isset($this->adultContent)) {
            $xml->writeElement("adultContent",$this->adultContent);
        }    
        if(isset($this->language)) {
            if(is_array($this->language)) {
                for($i = 0; $i < count($this->language); $i++) {
                    $xml->writeELement("Language",$this->language[$i]);
                }
            } else {
                $xml->writeElement("Language",$this->language);
            }
        }
        if(count($this->inputEncoding)) {
             if(is_array($this->inputEncoding)) {
                 for($i = 0; $i < count($this->inputEncoding); $i++) {
                     $xml->writeELement("InputEncoding",$this->inputEncoding[$i]);
                 }
             } else {
                 $xml->writeElement("InputEncoding",$this->inputEncoding);
             }
         }        
         if(count($this->outputEncoding)) {
              if(is_array($this->outputEncoding)) {
                  for($i = 0; $i < count($this->outputEncoding); $i++) {
                      $xml->writeELement("OutputEncoding",$this->OuputEncoding[$i]);
                  }
              } else {
                  $xml->writeElement("OuputEncoding",$this->OuputEncoding);
              }
          }         
          if(count($this->tags)) {
              $xml->writeElement("Tags",join($this->tags," "));
          }
          if(count($this->image)) {
              $xml->startElement("Image",$this->image["location"]);
              if($this->image["height"]) {
                    $xml->writeAttribute("height",$this->image["height"]);
                }
                if($this->image["width"]) {
                    $xml->writeAttribute("width",$this->image["width"]);
                }
                if($this->image["type"]) {
                    $xml->writeAttribute("type",$this->image["type"]);
                }
                $xml->endElement();
                
          }
        if(count($this->indexes) || isset($this->exampleQuery)) {
            $xml->startElement("Query");
            if(isset($this->exampleQuery)) {
                $xml->writeAttribute("role","example");
                $xml->writeAttribute("searchTerms",$this->exampleQuery);
            }
            if(count($this->indexes)) {
                $xml->startElement("zr:explain");
                $xml->writeAttribute("xmlns:zr","http://explain.z3950.org/dtd/2.1/");
                $xml->startElement("zr:indexInfo");
                for($i = 0; $i < count($this->indexes); $i++) {
                    $xml->startElement("zr:set");
                    $xml->writeAttribute("name",$this->indexes[$i]->name);
                    if($this->indexes[$i]->identifier) {
                        $xml->writeAttribute("identifier",$this->indexes[$i]->identifier);
                    }
                    $xml->endELement();
                    for($x = 0; $x < count($this->indexes[$i]->indexes); $x++) {
                        $xml->startElement("zr:index");
                        $xml->startElement("zr:map");
                        $xml->startElement("zr:name");
                        $xml->writeAttribute("set",$this->indexes[$i]->name);
                        $xml->text($this->indexes[$i]->indexes[$x]);
                        $xml->endElement();
                        $xml->endElement();
                        $xml->endELement();
                    }
                }
                $xml->endElement();
                $xml->endElement();
            }
            
            $xml->endElement();
        }
        $xml->endElement();
        return($xml->outputMemory(true));
                
    }
}
class JangleContextSet {
    public $name, $identifer;
    public $indexes = array();
    function fromConnectorResponse($json) {
        $this->name = $json->name;
        if($json->identifier) {
            $this->identifier = $json->identifier;
        }
        if($json->indexes) {
            $this->indexes = $json->indexes;
        }
    }
}
?>