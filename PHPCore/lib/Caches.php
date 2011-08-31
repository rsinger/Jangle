<?php
require 'Cache.php';
require 'Cache/Function.php';
class CacheSingleton {
    protected $config;
    public $cache;
    protected function __construct() {
        $conf = Spyc::YAMLLoad('config/config.yml');
        $this->config = $conf["cache_location"];
    }

}

class PageCache extends CacheSingleton {
    private static $instance;
    function __construct() {
        parent::__construct();
        $this->cache = new Cache('file',array("cache_dir"=>$this->config["directory"]));
    }
    
    function get($id) {
        return $this->cache->get($id);        
    }
    
    function save($id, $stuffToCache) {
        return $this->cache->save($id, $stuffToCache);
    }
    public function singleton() {
        if(!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }
        return self::$instance;
    }    
    
}

class SimpleCache extends CacheSingleton {
    private static $instance, $dir;
    function __construct() {
        parent::__construct();
        $this->dir = $this->config["directory"];
    }
    function get($key) {
        
        if(file_exists($this->dir."/".md5($key))) {
            $file = fopen($this->dir."/".md5($key),"rb");
            $rawcache = fread($file, filesize($this->dir."/".md5($key)));
            $cache = unserialize(base64_decode($rawcache));
            fclose($file);
        } else {
            $cache = false;
        }
        return $cache;
    }
    function save($key, $data) {
        $file = fopen($this->dir."/".md5($key),"wb");
        fwrite($file, base64_encode(serialize($data)));
        fclose($file);
    }
    public function singleton() {
        if(!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }
        return self::$instance;
    }    
    
}

class FunctionCache extends CacheSingleton {
    private static $instance;
    function __construct() {
        parent::__construct();
        $this->cache = new Cache_Function('file',array("cache_dir"=>$this->config["directory"],"filename_prefix"=>$this->config["function"]));
    }
    
    function call ($func,$args) {
        return $this->cache->call($func, $args);        
    }
    
    function get($id) {
        return $this->cache->get($id);        
    }    
    
    function save($id, $stuffToCache) {
        return $this->cache->save($id, $stuffToCache);
    }
    public function singleton() {
        if(!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }
        return self::$instance;
    }    
    
}
?>