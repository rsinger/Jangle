<?php
require_once 'Contact_Vcard_Build.php';
require 'File/MARC.php'; 
require 'lib/cqlParser.php';

class Entity {
    protected $db, $config, $limit;    
    function __construct($config) {
        $this->config = $config;
        if(!$this->db) {
            $this->db = new Database($config);
        }
        $this->setLimit();
    }    

    function setLimit() {
        if ($this->config["entities"][$this->entity]["maximum_records"]) {
            $this->limit = $this->config["entities"][$this->entity]["maximum_results"];
        } else {
            $this->limit = $this->config["global_options"]["maximum_results"];
        }
    }   
    function getIdList($ids) {
        if(preg_match("/^\d*$/", $ids)) {
            $idList = array($ids);
        } elseif (preg_match("/^{\d[,;]}+\d+$/", $ids)) {
            $idList = preg_split("/[,;]/",$ids);
        } else {
            $idList = array();
            foreach((preg_split("/[;,]/",$ids)) as $id) {
                if(preg_match("/^\d*$/", $id)) {
                    array_push($idList,$id);
                } else {
                    $range = explode("-",$id);
                    $idList = array_merge($idList,range($range[0],$range[1]));
                }
            }
        }  
        return $idList;
    }   
    
    function count($ids=NULL) {
        if($ids == NULL) {
            $result = mysql_query("SELECT count(*) FROM ".$this->tablename);
            $row = mysql_fetch_row($result);
            $count = $row[0];
            settype($count, "int");
        } else {
            $idList = $this->getIdList($ids);
            $count = count($idList);            
        }
        return $count;
    }    
    
    function getContentType($type) {
        if($type == "atom") {
            return;
        }
        return $this->config["record_types"][$type]["content-type"];
    }
    
    function getRecordType($type) {
        if($type == "atom") {
            return;
        }
        return $this->config["record_types"][$type]["uri"];        
    }
    
    function setAlternateRecordTypes($thisType, &$resource) {
        $record_types = $this->config["entities"][$this->entity]["record_types"];
        for($i = 0; $i < count($record_types); $i++) {
            if($record_types[$i] != $thisType) {
                $resource->addAlternateFormat($this->config["record_types"][$record_types[$i]]["uri"], $resource->baseUri()."?format=".$record_types[$i]);
            }
        }
    }
    
    function setStylesheet($format) {
        if($format == "atom") {
            return;
        }
        if($this->config["record_types"][$format]["stylesheets"]) {
            if($this->config["record_types"][$format]["stylesheets"]["item"]) {
                if(array_search($this->entity, $this->config["record_types"][$format]["stylesheets"]["item"]["entities"]) !== False) {
                    return $this->config["record_types"][$format]["stylesheets"]["item"]["uri"];
                }
            }
        }
        return;
    }
    
    function relationMap($relation) {
        switch($relation){
            case 'actors':
            $resources = new Actors($this->config);
            break;
            case 'collections':
            $resources = new Collections($this->config);
            break;
            case 'items':
            $resources = new Items($this->config);
            break;
            case 'resources':
            $resources = new Resources($this->config); 
            break;
        }    
        return $resources;
    }
    
    function setFormatFunction($format) {
        if($this->config["entities"][$this->entity]["method_aliases"] && $this->config["entities"][$this->entity]["method_aliases"][$format]) {
            return $this->config["entities"][$this->entity]["method_aliases"][$format];
        } else {
            return $format;
        }
    }    

    function atom($data) {}    
    
    function cqlToSql($query) {
        $parser = new CQLParser(stripslashes($query));
        $nodeTree = $parser->query();
        $tables = array();
        $terms = array();
        $order = array();
        $where = $this->cqlNodeWalker($nodeTree, $tables, $terms, $order);
        return(array("tables"=>$tables, "terms"=>$terms, "where"=>$where, "order"=>$order));
    }
    
    function search($query, $offset, $record_format, $limit=NULL) {
        if($limit == NULL) {
            $limit = $this->limit;
        }
        $sqlParts = $this->cqlToSql($query);
        $options = array("offset"=>$offset, "limit"=>$limit);
        $sqlString = $this->buildSearchQuery($sqlParts, $options);        
        return $this->getResources($sqlString, $record_format);
    }
    
    function searchCount($query) {
        $sqlParts = $this->cqlToSql($query);
        return $this->doSearchCount($sqlParts);
    }

    function cqlNodeWalker(&$node,&$tables,&$terms,&$order) {
        switch(get_class($node)){
            case("SearchClause"):
            $where = $this->buildSqlFromCql($node, $tables, $terms);
            break;
            case("Triple"):
            $lwhere = $this->cqlNodeWalker($node->leftOperand,$tables,$terms,$order);
            $rwhere = $this->cqlNodeWalker($node->rightOperand,$tables,$terms,$order);
            $join = $node->boolean->value;
            $where = "(".$lwhere.") ".$join." (".$rwhere.")";
            break;
        }
        if($node->sortKeys) {
            for($i = 0; $i < count($node->sortKeys); $i++) {
                $this->cqlSortToSqlOrder($node->sortKeys[$i],$order);
            }
        }
        return $where;
    }

    function cqlRelationsToSqlRelations($rel) {
      $map = array("any"=>"like","all"=>"like","="=>"like","=="=>"=","<>"=> "NOT");
      if($map[$rel]) {
          return $map[$rel];
      } else {
          return $rel;
      }
    }
    function entity() {
        return $this->entity;
    }
}
class Actors extends Entity {
    protected $tablename = 'member';
    protected $entity = 'actors';
    public $mixed;
    function __construct($config, $mixedFeed=false) {
       parent::__construct($config);
       $this->mixed = $mixedFeed;
    }

    
    function all($offset = 0, $record_format) {
        $query = sprintf("SELECT * FROM member ORDER BY last_change_dt DESC LIMIT %d, %d", 
          mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        return $this->getResources($query, $record_format);
    }
    
    function find($ids, $offset, $record_format) {        
        $idList = $this->getIdList($ids);
        $query = sprintf("SELECT * FROM member WHERE mbrid IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        return $this->getResources($query, $record_format);
    }
    
    function relationFindByFilter($ids, $relation, $filter, $offset, $record_format) {        
        $idList = $this->getIdList($ids);
        $resources = $this->relationMap($relation);
        if($relation == "items") {
            if($filter == "copy") {
                return($this->relationFind($ids, $relation, $offset, $record_format));
            } elseif ($filter == "hold") {
                $query = sprintf("SELECT biblio_copy.*, biblio_status_dm.description FROM biblio_copy, biblio_status_dm, biblio_hold WHERE biblio_copy.status_cd = biblio_status_dm.code AND biblio_hold.mbrid IN (%s) ORDER BY status_begin_dt DESC LIMIT %d, %d", 
                  mysql_real_escape_string($idList), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
            }
        }
        return $resources->getResources($query, $record_format, 'actor');
    }
    
    function relationFilterCount($ids, $relation, $filter) {
        $idList = $this->getIdList($ids);
        if($relation == "items") {
            if($filter == "copy") {
                return($this->relationCount($ids,$relation));
            } elseif($filter == "hold") {
                $query = sprintf("SELECT count(biblio_copy.bibid) FROM biblio_copy, biblio_status_dm, biblio_hold WHERE biblio_copy.status_cd = biblio_status_dm.code AND biblio_hold.mbrid IN (%s)", 
                  mysql_real_escape_string($idList), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));                
            }
        }    
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0],"int");        
        return $row[0];
    }    
    
    function getResources($query, $record_format, $relation = null) {
        $result = mysql_query($query);
        $results = array();
        if (!$result) {
            $message  = 'Invalid query: ' . mysql_error() . "\n";
            $message .= 'Whole query: ' . $query;
            die($message);
        }        
        $actors = array();
        while ($row = mysql_fetch_assoc($result)) {
            $datum = new JangleDataItem($this->config["base_uri"]."/actors/".$row['mbrid'], $row["last_change_dt"]);
            $datum->setTitle($row["last_name"].", ".$row["first_name"]);
            $datum->setContentType(call_user_func(array("Actors", "getContentType"), $record_format));
            $datum->setFormat($this->getRecordType($record_format));
            if($this->mixed) {
                $this->setStylesheet($record_format);
            }
            $this->setAlternateRecordTypes($record_format, $datum);
            $actual_format = $this->setFormatFunction($record_format);
            $datum->setContent(call_user_func(array("Actors", $actual_format), $row));
            if($relation == 'item') {                
                $datum->addLink('via',$this->config["base_uri"].'/item/'.$row['barcode_nmbr'], 'application/atom+xml');                    
            }    
            array_push($results, $datum);
            array_push($actors, $row['mbrid']);
        }         
        if(count($actors) > 0) {
            $this->addItemRelationships($actors, $results);
        }
        return $results;        
    }
    
    function relationFind($ids, $relation, $offset, $record_format) {
        
        $idList = $this->getIdList($ids);
        $resources = $this->relationMap($relation);
        if($relation == "items") {
            $query = sprintf("SELECT biblio_copy.*, biblio_status_dm.description FROM biblio_copy, biblio_status_dm WHERE biblio_copy.status_cd = biblio_status_dm.code AND mbrid IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } 
        return $resources->getResources($query, $record_format, 'actor');
    }
    
    function relationCount($ids, $relation) {
        $idList = $this->getIdList($ids);
        if($relation == "items") {
            $query = sprintf("SELECT count(bibid) FROM biblio_copy WHERE mbrid IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } 
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0],"int");        
        return $row[0];
    }    
    
    function addItemRelationShips(&$actorIds,&$dataItems){
        $query = sprintf("SELECT DISTINCT mbrid FROM biblio_copy WHERE mbrid in (%s)", join($actorIds,","));
        $result = mysql_query($query);
        $actors = array();
        while($row = mysql_fetch_assoc($result)) {
            array_push($actors, $this->config["base_uri"]."/actors/".$row['mbrid']);
        }
        foreach($dataItems as $resource) {
            if(in_array($resource->id, $actors)) {
                $resource->addRelationship("item");
            }
        }        
    }
    
    
    function vcard($data) {
        $vcard = new Contact_Vcard_Build();
        $vcard->setFormattedName($data["first_name"]." ".$data["last_name"]);        
        $vcard->setName($data["last_name"], $data["first_name"],NULL,NULL,NULL);
        if($data["email"]) {
            $vcard->addEmail($data['email']);
        }
        $address = explode("\n",$data["address"]);
        $cityregionpost = explode(",",$address[1]);
        $regionpost = explode(" ",$cityregionpost[1]);
        $vcard->addAddress(NULL, NULL, $address[0], $cityregionpost[0], $regionpost[0], $regionpost[1], NULL);
        $vcard->addParam("TYPE","HOME");
        if($data["work_phone"]) {
            $vcard->addTelephone($data["work_phone"]);
            $vcard->addParam("TYPE","WORK");
        }
        if($data["home_phone"]) {
            $vcard->addTelephone($data["home_phone"]);
            $vcard->addParam("TYPE","HOME");
        }        
        $vcard->setURL($this->config["base_uri"]."actors/".$data["mbrid"]);
        $vcard->setUniqueID($data["barcode_nmbr"]);
        return $vcard->fetch();
    }
    
}

class Collections extends Entity {
    protected $tablename = 'collection_dm';
    protected $entity = 'collections';
    public $mixed;
    function __construct($config, $mixedFeed=false) {
       parent::__construct($config);
       $this->mixed = $mixedFeed;
    }

    
    function all($offset = 0, $record_format) {
        $query = sprintf("SELECT * FROM collection_dm ORDER BY code DESC LIMIT %d, %d", 
          mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));

        return $this->getResources($query, $record_format);
    }
    
    function find($ids, $offset, $record_format) {
        
        $idList = $this->getIdList($ids);

        $query = sprintf("SELECT * FROM collection_dm WHERE code IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        return $this->getResources($query, $record_format);
    }
    
    function relationFind($ids, $relation, $offset, $record_format) {
        
        $idList = $this->getIdList($ids);
        $resources = $this->relationMap($relation);
        if($relation == "resources") {
            $query = sprintf("SELECT * FROM biblio WHERE collection_cd IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } 
        return $resources->getResources($query, $record_format, 'collection');
    }
    
    function relationCount($ids, $relation) {
        $idList = $this->getIdList($ids);
        if($relation == "resources") {
            $query = sprintf("SELECT count(bibid) FROM biblio WHERE collection_cd IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } 
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0],"int");        
        return $row[0];
    }  
    
    function relationFindByFilter($ids, $relation, $filter, $offset, $record_format) {        
        $idList = $this->getIdList($ids);
        $resources = $this->relationMap($relation);
        if($relation == "resources") {
            if($filter == "opac") {
                $query = sprintf("SELECT biblio.* FROM biblio WHERE biblio.collection_cd IN (%s) AND biblio.opac_flg = 'Y' ORDER BY last_change_dt DESC LIMIT %d, %d", 
                mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
            }
        }
        return $resources->getResources($query, $record_format, 'collection');
    }
    
    function relationFilterCount($ids, $relation, $filter) {
        $idList = $this->getIdList($ids);
        if($relation == "resources") {
            if($filter == "opac") {
                $query = sprintf("SELECT count(biblio.bibid) FROM biblio WHERE biblio.collection_cd IN (%s) AND biblio.opac_flg = 'Y' LIMIT %d, %d", 
                mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
            }
        }    
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0],"int");        
        return $row[0];
    }      
    
    function getResources($query, $record_format, $relation = false) {
        $result = mysql_query($query);
        $results = array();

        if (!$result) {
            $message  = 'Invalid query: ' . mysql_error() . "\n";
            $message .= 'Whole query: ' . $query;
            die($message);
        }        
        $collections = array();
        $metaQuery = mysql_query("show table status like 'collection_dm'");
        $metaRow = mysql_fetch_assoc($metaQuery);
        $lastModified = $metaRow["Update_time"];
        while ($row = mysql_fetch_assoc($result)) {
            $datum = new JangleDataItem($this->config["base_uri"]."/collections/".$row['code'], $lastModified);
            $datum->setTitle($row["description"]);
            $datum->setContentType(call_user_func(array("Collections", "getContentType"), $record_format));
            $datum->setFormat($this->getRecordType($record_format));
            if($this->mixed) {
                $this->setStylesheet($record_format);
            }            
            $this->setAlternateRecordTypes($record_format, $datum);    
            $actual_format = $this->setFormatFunction($record_format);  
            $datum->setContent(call_user_func(array("Collections", $actual_format), $row, $datumconfig["entities"]));
            array_push($results, $datum);
            array_push($collections, $row['code']);
            if($relation == 'resource') {                
                $datum->addLink('via',$this->config["base_uri"].'/resources/'.$row['bibid'], 'application/atom+xml');                    
            }            
        }         
        $this->addResourceRelationships($collections, $results);
        return $results;        
    }
    
    function addResourceRelationShips(&$collectionIds,&$dataItems){
        $query = sprintf("SELECT DISTINCT collection_cd FROM biblio WHERE collection_cd in (%s)", join($collectionIds,","));
        $result = mysql_query($query);
        $collections = array();
        while($row = mysql_fetch_assoc($result)) {
            array_push($collections, $config["base_uri"]."/collections/".$row['collection_cd']);
        }
        foreach($dataItems as $resource) {
            if(in_array($resource->id, $collections)) {
                $resource->addRelationship("resource");
            }
        }        
    }  
    
    function dc($data, $resource) {
        $xml = new xmlWriter();
        $xml->openMemory();
        $xml->startElement("rdf:Description");
        $xml->writeAttribute("xmlns:rdf","http://www.w3.org/1999/02/22-rdf-syntax-ns#");
        $xml->writeAttribute("xmlns:dc","http://purl.org/dc/elements/1.1/");
        $xml->writeAttribute("rdf:about",$resource->id);
        $xml->writeElement("dc:title",$resource->title);
        $xml->writeElement("dc:identifier", $resource->id);
        $xml->startElement("dc:type");
        $xml->writeAttribute("rdf:resource","http://purl.org/dc/dcmitype/Collection");
        $xml->text("Collection");
        $xml->endElement();
        $xml->startElement("rdf:Type");
        $xml->writeAttribute("rdf:resource","http://purl.org/dc/dcmitype/Collection");
        $xml->endElement();        
        $xml->writeElement("dc:source","http://jangle.org/vocab/Entity#Collection");
        $xml->endElement();
        return($xml->outputMemory(true));        
    }

    function atom($data) {}  
}

class Items extends Entity {
    protected $tablename = 'biblio_copy';
    protected $entity = 'items';
    public $mixed;
    function __construct($config, $mixedFeed=false) {
       parent::__construct($config);
       $this->mixed=$mixedFeed;
    }  
    
    function all($offset = 0, $record_format) {
        $query = sprintf("SELECT biblio_copy.*, biblio_status_dm.description FROM biblio_copy, biblio_status_dm WHERE biblio_copy.status_cd = biblio_status_dm.code ORDER BY status_begin_dt DESC LIMIT %d, %d", 
          mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        return $this->getResources($query, $record_format);
    }
    
    function find($ids, $offset, $record_format) {        
        $idList = $this->getIdList($ids);
        $query = sprintf("SELECT biblio_copy.*, biblio_status_dm.description FROM biblio_copy, biblio_status_dm WHERE biblio_copy.status_cd = biblio_status_dm.code AND barcode_nmbr IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        return $this->getResources($query, $record_format);
    }
    
    function findByFilter($category, $offset, $record_format) {    
        if($category == "copy") {  
            return $this->all($offset, $record_format);  
        } elseif ($category == "hold") {
            $query = sprintf("SELECT biblio_copy.*, biblio_status_dm.description FROM biblio_copy, biblio_status_dm, biblio_hold WHERE biblio_copy.status_cd = biblio_status_dm.code AND biblio_copy.bibid = biblio_hold.bibid AND biblio_copy.copyid = biblio_hold.copyid ORDER BY status_begin_dt DESC LIMIT %d, %d", mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));            
        }
        return $this->getResources($query, $record_format);
    }  
    
    function filterCount($category) {    
        if($category == "copy") {    
            return $this->count();
        } elseif ($category == "hold") {
            $query = sprintf("SELECT count(biblio_copy.bibid) FROM biblio_copy, biblio_status_dm, biblio_hold WHERE biblio_copy.status_cd = biblio_status_dm.code AND biblio_copy.bibid = biblio_hold.bibid AND biblio_copy.copyid = biblio_hold.copyid");
        }
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0], "int");        
        return $row[0];
    }    

    function relationFind($ids, $relation, $offset, $record_format) {        
        $idList = $this->getIdList($ids);
        $resources = $this->relationMap($relation);
        if($relation == "resources") {
            $query = sprintf("SELECT biblio.*, biblio_copy.barcode_nmbr FROM biblio, biblio_copy WHERE biblio_copy.bibid = biblio.bibid AND biblio_copy.barcode_nmbr IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } elseif($relation == "actors") {
              $query = sprintf("SELECT member.*, biblio_copy.barcode_nmbr FROM member, biblio_copy WHERE biblio_copy.barcode_nmbr IN (%s) AND biblio_copy.mbrid = member.mbrid LIMIT %d, %d", 
            mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));            
        }
        return $resources->getResources($query, $record_format, 'item');
    }
    
    function relationCount($ids, $relation) {
        $idList = $this->getIdList($ids);
        if($relation == "resources") {
            $query = sprintf("SELECT count(biblio.bibid) FROM biblio, biblio_copy WHERE biblio_copy.bibid = biblio.bibid AND biblio_copy.barcode_nmbr IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } elseif($relation == "actors") {
              $query = sprintf("SELECT count(member.mbrid) FROM member, biblio_copy WHERE biblio_copy.barcode_nmbr IN (%s) AND biblio_copy.mbrid = member.mbrid LIMIT %d, %d", 
            mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));            
        }       
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0],"int");
        return $row[0];        
    }    
            
    function getResources($query, $record_format, $relation = null) {
        $result = mysql_query($query);
        $results = array();

        if (!$result) {
            $message  = 'Invalid query: ' . mysql_error() . "\n";
            $message .= 'Whole query: ' . $query;
            die($message);
        }        
        while ($row = mysql_fetch_assoc($result)) {
            $datum = new JangleDataItem($this->config["base_uri"]."/items/".$row['barcode_nmbr'], $row['status_begin_dt']);
            if(preg_match("/in|dis/",$row[status_cd])) {
                $datum->setTitle("available");                
            } else {
                $datum->setTitle("not available");
            }
            $datum->setDescription($row["description"]);
            $datum->setContentType(call_user_func(array("Items", "getContentType"), $record_format));             
            $datum->setFormat($this->getRecordType($record_format));
            if($this->mixed) {
                $this->setStylesheet($record_format);
            }
            $this->setAlternateRecordTypes($record_format, $datum);            
            $actual_format = $this->setFormatFunction($record_format);            
            $datum->setContent(call_user_func(array("Items", $actual_format), $row, $datum));
            if($row["bibid"]) {
                $datum->addRelationship("resource");
                if($relation == 'resource') {
                    $datum->addLink('via',$this->config["base_uri"].'/resources/'.$row['bibid'], 'application/atom+xml');
                }
            }
            if($row["mbrid"]) {
                $datum->addRelationship("actor");
                if($relation == 'actor') {
                    $datum->addLink('via',$this->config["base_uri"].'/actors/'.$row['mbrid'], 'application/atom+xml');
                }                
            }
            if($row["opac_flg"] == "Y") {
                $datum->addCategory("opac");
            }
            $q = "SELECT count(*) FROM biblio_hold WHERE bibid = ".$row["bibid"]." AND copyid = ".$row["copyid"];
            $r = mysql_query($q);
            $cnt = mysql_fetch_row($r);
            if($cnt[0] > 0) {
                $datum->addCategory("hold");
            }
            $datum->addCategory("copy");
            array_push($results, $datum);
        }                 
        return $results;        
    }
    
    function dlfexpanded($data, $resource) { 
        $xml = new xmlWriter();
        $xml->openMemory();              
        $xml->startElement("dlf:record");
        $xml->writeAttribute("xmlns:dlf","http://diglib.org/ilsdi/1.0");
        $xml->writeAttribute("xmlns:xsi","http://www.w3.org/2001/XMLSchema-instance");
        $xml->writeAttribute("xsi:schemaLocation","http://diglib.org/ilsdi/1.0 http://diglib.org/architectures/ilsdi/schemas/1.0/dlfexpanded.xsd");
        $xml->startElement("dlf:bibliographic");
        $xml->writeAttribute("id",$this->config["base_uri"]."/resources/".$data["bibid"]);
        $xml->endElement();
        $xml->startElement("dlf:items");
        $xml->startElement("dlf:item");
        $xml->writeAttribute("id", $this->config["base_uri"]."/items/".$data["barcode_nmbr"]);
        $xml->startElement("dlf:simpleavailability");
        $xml->writeElement("dlf:identifier", $this->config["base_uri"]."/items/".$data["barcode_nmbr"]);
        $xml->writeElement("dlf:availabilitystatus", $resource->title);    
        $xml->writeElement("dlf:availabilitymsg", $data["description"]);        
        if($data["due_back_dt"]) {
            $xml->writeElement("dlf:dateavailable",$data["due_back_dt"]);
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        return($xml->outputMemory(true));
    }
    function daia($data, $resource) {
        $xml = new xmlWriter();
        $xml->openMemory();
        $xml->startElement("daia");
        $xml->writeAttribute("xmlns","http://ws.gbv.de/daia/");
        $xml->writeElement("timestamp",strftime("%Y-%m-%dT%H:%m:%s-%Z"));
        $xml->startElement("item");
        $xml->writeElement("id",$resource->id);
        $xml->writeElement("localid",$data["barcode_nmbr"]);
        $availability = array("service"=>"loan","available"=>"false");
        if(preg_match("/in|dis/",$data["status_cd"])) {
            $availability["available"] = "true";
        }
        if($data["status_cd"] == "out") {
            $now = time();
            $then = strtotime($data["due_back_dt"]);
            $delay = round((($now-$then)/86400));
            $availability["delay"] = "P".$delay."D";
        } elseif($data["status_cd"] == "out") {
            $availability["delay"] = "inf";
        } elseif($data["status_cd"] == "disp") {
            $availability["service"] = "presentation";
        }          
        $q = "SELECT material_cd FROM biblio WHERE bibid = "  . $data["bibid"];
        $r = mysql_query($q);
        $row = mysql_fetch_row($r);
        if($row[0] == 6) {
            $availability["service"] = "presentation";
        }
        $xml->startElement("availability");
        for($i = 0; $i < count($availability); $i++) {
            $xml->writeAttribute(key($availability), $availability[key($availability)]);
            next($availability);
        }
        $xml->startElement("message");
        $xml->writeAttribute("lang","en");
        $xml->text($data["description"]);
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        return($xml->outputMemory(true));        
    }    
}

class Resources extends Entity {
    protected $tablename = 'biblio';
    protected $entity = 'resources';
    public $mixed;
    function __construct($config,$mixedFeed=false) {
       parent::__construct($config);
       $this->mixed=$mixedFeed;
    }
    
    function all($offset = 0, $record_format) {
        $query = sprintf("SELECT * FROM biblio ORDER BY last_change_dt DESC LIMIT %d, %d", 
          mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        return $this->getResources($query, $record_format);
    }
    
    function find($ids, $offset, $record_format) {        
        $idList = $this->getIdList($ids);
        $query = sprintf("SELECT * FROM biblio WHERE bibid IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        return $this->getResources($query, $record_format);
    }
    
    function findByFilter($category, $offset, $record_format) {    
        if($category == "opac") {    
            $query = sprintf("SELECT * FROM biblio WHERE opac_flg = 'Y' ORDER BY last_change_dt DESC LIMIT %d, %d", 
                mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        }
        return $this->getResources($query, $record_format);
    }  
    
    function filterCount($category) {    
        if($category == "opac") {    
            $query = sprintf("SELECT count(bibid) FROM biblio WHERE opac_flg = 'Y' ORDER BY last_change_dt LIMIT %d, %d", 
                mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        }
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0], "int");        
        return $row[0];
    }    
    
    function relationFindByFilter($ids, $relation, $filter, $offset, $record_format) {        
        $idList = $this->getIdList($ids);
        $resources = $this->relationMap($relation);
        if($relation == "items") {
            if($filter == "copy") {
                return($this->relationFind($ids, $relation, $offset, $record_format));
            } elseif ($filter == "hold") {
                $query = sprintf("SELECT biblio_copy.*, biblio_status_dm.description FROM biblio_copy, biblio_status_dm, biblio_hold WHERE biblio_copy.status_cd = biblio_status_dm.code AND biblio_hold.bibid IN (%s) ORDER BY status_begin_dt DESC LIMIT %d, %d", 
                  mysql_real_escape_string($idList), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
            }
        }
        return $resources->getResources($query, $record_format, 'resource');
    }
    
    function relationFilterCount($ids, $relation, $filter) {
        $idList = $this->getIdList($ids);
        if($relation == "items") {
            if($filter == "copy") {
                return($this->relationCount($ids,$relation));
            } elseif($filter == "hold") {
                $query = sprintf("SELECT count(biblio_copy.bibid) FROM biblio_copy, biblio_status_dm, biblio_hold WHERE biblio_copy.status_cd = biblio_status_dm.code AND biblio_hold.bibid IN (%s)", 
                  mysql_real_escape_string($idList), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));                
            }
        }    
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0],"int");        
        return $row[0];
    }      

    function relationFind($ids, $relation, $offset, $record_format) {
        
        $idList = $this->getIdList($ids);
        $resources = $this->relationMap($relation);
        if($relation == "items") {
            $query = sprintf("SELECT biblio_copy.*, biblio_status_dm.description FROM biblio_copy, biblio_status_dm WHERE biblio_copy.status_cd = biblio_status_dm.code AND bibid IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } elseif($relation == "collections") {
              $query = sprintf("SELECT collection_dm.*, biblio.bibid FROM collection_dm, biblio WHERE biblio.bibid IN (%s) AND biblio.collection_cd = collection_dm.code LIMIT %d, %d", 
            mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));            
        }
        return $resources->getResources($query, $record_format, 'resource');
    }
    
    function relationCount($ids, $relation) {
        $idList = $this->getIdList($ids);
        if($relation == "items") {
            $query = sprintf("SELECT count(bibid) FROM biblio_copy WHERE bibid IN (%s) LIMIT %d, %d", 
          mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));
        } elseif($relation == "collections") {
              $query = sprintf("SELECT count(collection_dm.code) FROM collection_dm, biblio WHERE biblio.bibid IN (%s) AND biblio.collection_cd = collection_dm.code LIMIT %d, %d", 
            mysql_real_escape_string(join($idList,",")), mysql_real_escape_string($offset), mysql_real_escape_string($this->limit));            
        }       
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0],"int");        
        return $row[0];
    }
        
    function getResources($query, $record_format, $relation = null) {
        $result = mysql_query($query);
        $results = array();

        if (!$result) {
            $message  = 'Invalid query: ' . mysql_error() . "\n";
            $message .= 'Whole query: ' . $query;
            die($message);
        }        
        $resources = array();
        while ($row = mysql_fetch_assoc($result)) {
            $datum = new JangleDataItem($this->config["base_uri"]."/resources/".$row['bibid'],$row["last_change_dt"]);
            $datum->setTitle($row["title"]);
            if(preg_match("/\w/",$row["author"])) {
                $datum->setAuthor($row["author"]);
            }
            $datum->setFormat($this->getRecordType($record_format));
            if($this->mixed) {
                $this->setStylesheet($record_format);
            }
            if($this->config['entities']['resources']['url_template']) {
                $html_url = str_replace("{id}", $row['bibid'], $this->config['entities']['resources']['url_template']);
                $datum->addLink('alternate',$html_url,"text/html","Link to OPAC");
            }
            $this->setAlternateRecordTypes($record_format, $datum);            
            $datum->setContentType(call_user_func(array("Resources", "getContentType"), $record_format));
            $actual_format = $this->setFormatFunction($record_format);        
            $datum->setContent(call_user_func(array("Resources", $actual_format), $row));
            $datum->setCreated($row["create_dt"]);
            if($row["collection_cd"]) {
                $datum->addRelationship("collection");
                if($relation == 'collection') {                    
                    $datum->addLink('via',$this->config["base_uri"].'/collections/'.$row['collection_cd'], 'application/atom+xml');                    
                }
            }
            if($relation == 'item') {                
                $datum->addLink('via',$this->config["base_uri"].'/item/'.$row['barcode_nmbr'], 'application/atom+xml');                    
            }
            if($row["opac_flg"] == "Y") {
                $datum->addCategory("opac");
            }
            array_push($results, $datum);
            array_push($resources, $row['bibid']);
        }         
        if(count($resources) > 0) {
            $this->addItemRelationships($resources, $results);
        }
        return $results;        
    }
    
    function cqlSortToSqlOrder(&$node, &$order) {
        $map = array("rec.lastModificationDate"=>"biblio.last_change_dt","dc.date"=>"biblio.last_change_dt",
            "date"=>"biblio.last_change_dt","lastModificationDate"=>"biblio.last_change_dt",
            "rec.creationDate"=>"biblio.create_dt","creationDate"=>"biblio.create_dt", "dc.title"=>"biblio.title",
            "title"=>"biblio.title","dc.creator"=>"biblio.author","creator"=>"biblio.author",
            "dc.author"=>"biblio.author","author"=>"biblio.author","rec.identifier"=>"biblio.bibid");
        $index = '';
        if($node->index->prefix) {
            $index .= $node->index->prefix.".";            
        }
        $index .= $node->index->value;
        if($map[$index]) {
            $clause = $map[$index];
            
            if($node->modifiers) {
                foreach($node->modifiers as $mod) {
                    if($mod->type->prefix == "sort") {
                        if($mod->type->value == "descending") {
                            $sort = " DESC";
                        } elseif ($mod->type->value == "ascending") {
                            $sort = " ASC";
                        }
                    }
                }
                $clause .= $sort;             
            }
            if(!in_array($clause, $order)) {
                array_push($order, $clause);
            }
        }
    }
    
    function doSearchCount($parts) {
        if(!in_array("biblio",$parts["tables"])) {
            array_push($parts["tables"],"biblio");
        }    
        if(in_array("biblio", $parts["tables"]) && in_array("biblio_field", $parts["tables"])) {
            $parts["where"] = "(".$parts["where"] . ") AND (biblio.bibid = biblio_field.bibid)";
        }        
        $terms = array();
        for($i = 0; $i < count($parts["terms"]); $i++) {
            array_push($terms, mysql_real_escape_string($parts["terms"][$i]));
        }
        $query = vsprintf("SELECT COUNT(DISTINCT biblio.bibid) FROM ".join($parts["tables"],", ")." WHERE ".$parts["where"],$terms);
        $result = mysql_query($query);
        $row = mysql_fetch_row($result);
        settype($row[0], "int");
        return $row[0];        
    }
    
    function buildSearchQuery($parts, $options) {
        if(!in_array("biblio",$parts["tables"])) {
            array_push($parts["tables"],"biblio");
        }    
        
        if(in_array("biblio", $parts["tables"]) && in_array("biblio_field", $parts["tables"])) {
            $parts["where"] = "(".$parts["where"] . ") AND (biblio.bibid = biblio_field.bibid)";
        }
        $terms = array();
        for($i = 0; $i < count($parts["terms"]); $i++) {
            array_push($terms, mysql_real_escape_string($parts["terms"][$i]));
        }
        array_push($terms, mysql_real_escape_string($options["offset"]));
        array_push($terms, mysql_real_escape_string($options["limit"]));        
        $sql = "SELECT DISTINCT biblio.* FROM ".join($parts["tables"],", ")." WHERE ".$parts["where"];
        if($parts["order"]) {
            $sql .= " ORDER BY ".join($parts["order"], ", ");
        }
        $sql .= " LIMIT %d,%d";
        $query = vsprintf($sql,$terms);
        return $query;
    }    

    function buildSqlFromCql(&$node,&$tables,&$terms) {
        $cqlToSql = array("dc.title"=>array(array("table"=>"biblio","fields"=>array("title","title_remainder"))),
            "dc.creator"=>array(array("table"=>"biblio","fields"=>array("author")),
             array("table"=>"biblio_field","fields"=>array("field_data"),
             "conditions"=>array("AND (biblio_field.tag = '700' or biblio_field.tag = '710' AND biblio_field.subfield_cd = 'a')"))),
             "dc.subject"=>array(array("table"=>"biblio","fields"=>array("topic1","topic2","topic3","topic4","topic5"))),
             "rec.lastModificationDate"=>array(array("table"=>"biblio","fields"=>array("last_change_dt"))),
             "rec.creationDate"=>array(array("table"=>"biblio","fields"=>array("create_dt"))), 
             "cql.keywords"=>array(array("table"=>"biblio","fields"=>
             array("title","title_remainder","author","topic1","topic2","topic3","topic4","topic5")),
             array("table"=>"biblio_field","fields"=>array("field_data"))));
        $aliases = array("title"=>"dc.title","creator"=>"dc.creator","dc.date"=>"rec.lastModificationDate",
            "date"=>"rec.lastModificationDate","keywords"=>"cql.keywords","cql.allIndexes"=>"cql.keywords",
            "cql.anyIndex"=>"cql.keywords","allIndexes"=>"cql.keywords","anyIndexes"=>"cql.keywords");


        $whereClause = '';
        $cqlIndex = '';
        if($node->index->prefix) {
            $cqlIndex .= $node->index->prefix.".";
        }
        $cqlIndex .= $node->index->value;
        if($cqlToSql[$cqlIndex] || $cqlToSql[$aliases[$cqlIndex]]) {
            if($cqlToSql[$cqlIndex]) {
                $index = $cqlToSql[$cqlIndex];
            } else {
                $index = $cqlToSql[$aliases[$cqlIndex]];
            }
            $thisClause = NULL;
            foreach($index as $table) {
                if(!in_array($table["table"],$tables)) {
                    array_push($tables, $table["table"]);
                }
                if(preg_match("/^all$|^any$/",$node->relation->value)) {
                    $queryTerms = explode(" ",$node->term->value);
                } else {
                    $queryTerms = array($node->term->value);
                }   
                $allClauses = array();
                foreach($queryTerms as $term) {
                    $clauses = array();
                    foreach($table["fields"] as $field) {                        
                        $clause = "lower(".$table["table"].".".$field.")";
                        $relation = '';
                        if($node->relation->prefix) {
                            $relation .= $node->relation->prefix.".";
                        }
                        $relation .= $node->relation->value;
                        $clause .= " ".$this->cqlRelationsToSqlRelations($relation)." '%s'";
                        array_push($clauses, $clause);
                        if(preg_match("/^all$|^=$|^any$/",$node->relation->value)) {                        
                            array_push($terms,"%".strtolower($term) ."%");
                        } else {
                            array_push($terms, strtolower($term));
                        }
                    }
                    array_push($allClauses, "(".join($clauses, " OR ").")");
                }

                if($table["conditions"]) {
                    $allClauses[(count($allClauses)-1)] .= " ".join($table["conditions"]," ");
                }
                if($node->relation->value == "all") {
                    $thisClause = "(".join($allClauses, " AND ").")";
                } else {
                    $thisClause = "(".join($allClauses, " OR ").")";
                }
                $whereClause .= $thisClause;
                if(array_search($table, $index) !== (count($index) - 1)) {
                    $whereClause .= " OR ";
                }
                                 
            }
        } else {
            $whereClause .= $this->specialIndexesToSql($node, $tables, $term);
        }
        return $whereClause;
    }

    function specialIndexesToSql(&$node,&$tables,&$terms) {
        $whereClause = '';
        $index = '';
        if($node->index->prefix) {
            $index .= $node->index->prefix.".";
        }
        $index .= $node->index->value;
        $relation = '';
        if($node->relation->prefix) {
            $relation .= $node->relation->prefix.".";
        }
        $relation .= $node->relation->value;
        if($index == "dc.identifier") {
            if(preg_match("/^".$this->config["base_uri"]."\/resources\/(\d*)$/", $node->term->value, $m)){
                if(!in_array("biblio",$tables)){
                    array_push($tables, "biblio");
                }               
                $whereClause .= "biblio.bibid ".$this->cqlRelationsToSqlRelations($relation) . " '%s'";
                array_push($terms, $m[1]);
            } elseif(preg_match("/urn:issn:(.*)/", $node->term->value, $m)) {
                if(!in_array("biblio_field", $tables)) {
                    array_push($tables, "biblio_field");
                }
                $whereClause .= "(biblio_field.field_data ".$this->cqlRelationsToSqlRelations($relation)." '%s' AND biblio_field.tag='022' AND biblio_field.subfield_cd='a')";
                array_push($terms, $m[1]);
            } elseif(preg_match("/urn:isbn:(.*)/", $node->term->value, $m)) {
                if(!in_array("biblio_field", $tables)) {
                    array_push($tables, "biblio_field");
                }
                $whereClause .= "(biblio_field.field_data ".$this->cqlRelationsToSqlRelations($relation)." '%s' AND biblio_field.tag='020' AND biblio_field.subfield_cd='a')";
                array_push($terms, $m[1]);
            } elseif(preg_match("/info:oclcnum\/(.*)/", $node->term->value, $m)) {
                if(!in_array("biblio_field", $tables)) {
                    array_push($tables, "biblio_field");
                }
                $whereClause .= "(biblio_field.field_data ".$this->cqlRelationsToSqlRelations($relation)." '%s' AND biblio_field.tag='035' AND biblio_field.subfield_cd='a')";
                array_push($terms, $m[1]);
            } else {
                foreach(array("biblio_field","biblio") as $t) {
                    if(!in_array($t,$tables)) {
                        array_push($tables, $t);
                    }
                }
                $clauses = array("biblio.bibid = %d",
                "(biblio_field.field_data ".$this->cqlRelationsToSqlRelations($relation)." '%s' AND biblio_field.tag='022' AND biblio_field.subfield_cd='a')",
                "(biblio_field.field_data ".$this->cqlRelationsToSqlRelations($relation)." '%s' AND biblio_field.tag='020' AND biblio_field.subfield_cd='a')",
                "(biblio_field.field_data ".$this->cqlRelationsToSqlRelations($relation)." '%s' AND biblio_field.tag='035' AND biblio_field.subfield_cd='a')"
                );
                $whereClause .= "(".join($clauses, " OR ").")";
                for($i = 0; $i < 4; $i++) {
                    array_push($terms, $node->term->value);
                }
            }
        } elseif ($index == "rec.identifier") {
            if(!in_array("biblio",$tables)) {
                array_push($tables, "biblio");
            }                
            $whereClause .= "biblio.bibid ".$this->cqlRelationsToSqlRelations($relation)." ?";                
            if(preg_match("/^".$this->config["base_uri"]."\/resources\/(\d*)$/", $node->term->value, $m)){                    
                array_push($terms, $m[1]);
            } else {
                array_push($terms, $node->term->value);
            }
        } elseif ($index == "rec.collectionName") {
            if($node->term->value == "opac") {
                if(!in_array("biblio",$tables)) {
                    array_push($tables, "biblio");
                }
                $whereClause .= "biblio.opac_flg ".$this->cqlRelationsToSqlRelations($relation)." 'Y'";
            }
        }
        return("(".$whereClause.")");
    }
    
    
    function addItemRelationShips(&$resourceIds,&$dataItems){
        $query = sprintf("SELECT DISTINCT bibid FROM biblio_copy WHERE bibid in (%s)", join($resourceIds,","));
        $result = mysql_query($query);
        if (!$result) {
            $message  = 'Invalid query: ' . mysql_error() . "\n";
            $message .= 'Whole query: ' . $query;
            die($message);
        }
        $resources = array();
        while($row = mysql_fetch_assoc($result)) {
            array_push($resources, $this->config["base_uri"]."/resources/".$row['bibid']);
        }
        foreach($dataItems as $resource) {
            if(in_array($resource->id, $resources)) {
                $resource->addRelationship("item");
            }
        }        
    }  
    
    function buildMarc($bibData) {
        $mrc = new File_MARC_Record();
        $mrc->appendField(new File_MARC_Control_Field("001", $bibData["bibid"]));
        $ldr = '                       ';
        $ohOhEight = strftime("%Y%m%d",strtotime($bibData['create_dt'])).'                           ';
        switch($bibData["material_cd"]) {
            case(1):
            $ldr[6] = 'j';
            $ldr[7] = 'm';
            break;
            case(2):
            $ldr[6] = 'a';
            $ldr[7] = 'm';
            break;
            case(3):
            $ldr[6] = 'j';
            $ldr[7] = 'm';
            break;
            case(4):
            $ldr[6] = 'm';
            $ldr[7] = 'm';
            break;
            case(5):
            $ldr[6] = 'p';
            $ldr[7] = 'i';
            break;
            case(6):
            $ldr[6] = 'a';
            $ldr[7] = 's';
            break;
            case(7):
            $ldr[6] = 'e';
            $ldr[7] = 'm';
            break;
            case(8):
            $ldr[6] = 'g';
            $ldr[7] = 'm';          
            break;
            default:
            $ldr[6] = 'a';
            $ldr[7] = 'm';
            break;                          
        }
        $subfields = array(new File_MARC_Subfield("a", $bibData["title"]));
        if($bibData["title_remainder"]) {
            array_push($subfields, new File_MARC_Subfield("b", $bibData["title_remainder"]));
        }
        if ($bibData["responsibility_stmt"]) {
            array_push($subfields, new File_MARC_Subfield("c", $bibData["responsibility_stmt"]));
        }
        $title = new File_MARC_Data_Field("245", $subfields, "0", "0");
        $mrc->appendField($title);
        $author = new File_MARC_Data_Field("100",
            array(new File_MARC_Subfield("a", $bibData["author"])), "1", NULL);
        $mrc->appendField($author);            
        $subjects = array($bibData["topic1"],$bibData["topic2"],$bibData["topic3"],$bibData["topic4"],$bibData["topic5"]);

        for($i = 0; $i < count($subjects); $i++) {
            $sub = new File_MARC_Data_field("650",
                array(new File_MARC_Subfield("a", $subjects[$i])), NULL, "4");
            $mrc->appendField($sub);
        }
        
        $query = "SELECT * from biblio_field WHERE bibid = " . $bibData['bibid'] . " GROUP BY fieldid";
        $result = mysql_query($query);
        while($row = mysql_fetch_assoc($result)) {
            if(!isset($fieldId)) {
                $fieldId = $row["fieldid"];
                if($row["ind1_cd"] != "N") {
                    $ind1 = $row["ind1_cd"];
                } else {
                    $ind1 = NULL;
                }
                if($row["ind2_cd"] != "N") {
                    $ind2 = $row["ind2_cd"];
                } else {
                    $ind2 = NULL;
                }                
                $field = new File_MARC_Data_Field(str_pad($row["tag"],3,"0",STR_PAD_LEFT), $ind1, $ind2);
            } 
            if ($fieldId == $row["fieldid"]) {
                $field->appendSubfield(new File_MARC_Subfield($row["subfield_cd"], $row["field_data"]));
            } else {
                $mrc->appendField($field);
                $fieldId = $row["fieldid"];
                if($row["ind1_cd"] != "N") {
                    $ind1 = $row["ind1_cd"];
                } else {
                    $ind1 = NULL;
                }
                if($row["ind2_cd"] != "N") {
                    $ind2 = $row["ind2_cd"];
                } else {
                    $ind2 = NULL;
                }                
                $field = new File_MARC_Data_Field(str_pad($row["tag"],3,"0",STR_PAD_LEFT), $ind1, $ind2);                
                $field->appendSubfield(new File_MARC_Subfield($row["subfield_cd"], $row["field_data"]));                
            }
        }        
        $mrc->setLeader($ldr);
        $mrc->appendField(new File_MARC_Control_Field("008",$ohOhEight));
        return $mrc;      
        
    }
    
    function marcxml($data) {

        $xml = simplexml_load_string($this->buildMarc($data)->toXML());
        $ns = $xml->registerXPathNamespace('m', 'http://www.loc.gov/MARC21/slim');
        $rec = $xml->xpath("/m:collection/m:record");
        $rec[0]->addAttribute('xmlns','http://www.loc.gov/MARC21/slim');
        return $rec[0]->asXML();
    }
    
    function marc($data) {
        return base64_encode($this->buildMarc($data)->toRaw());
    }

}
class Database {
    function __construct($config) {
        $conn = $config["database"]["development"];
        $db = mysql_connect($conn["host"],$conn["username"],$conn["password"]);
        if (!$db) {
            die('Could not connect: ' . mysql_error());
        }        
        mysql_select_db($conn["database"]);
      
        return $db;
    }
}
?>