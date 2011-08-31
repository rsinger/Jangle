require 'rubygems'
require 'sinatra'
require 'yaml'
require 'json'
require 'cgi'
require 'net/http'
require 'open-uri'
require 'rfuzz/session'
require 'xml/xslt'
require 'pstore'
require 'zlib'
require 'base64'

configure do 
  # Load our configuration file.
  CONFIG = YAML.load_file('config.yml')
  # Delete our cache
  File.delete("#{CONFIG["cache_location"]}/connector_responses.pstore")
end

get '/services' do
  redirect '/services/'
end

# Returns the service document.  This is the only mandatory, predefined route in the Jangle spec.
get '/services/' do
  header 'Content-Type' => 'application/atomsvc+xml'
  @services = []
  CONFIG["services"].each do |svc, vals|    
    @services << Jangle::Service.new_from_yaml(svc, vals).get_service_document
  end
  builder :service_document
end  

# All other requests are assumed to be proxied to their respective connector.
get '/:service/*' do
  unless CONFIG["services"][params[:service]]
    throw :halt, [404,"Not Found"]
  end
  @service = Jangle::Service.new_from_yaml(params[:service], CONFIG["services"][params[:service]])
  # Right now it just sends the request as is, but some method mapping is probably in order
  # to allow for both REST and JSON-RPC connectors.
  path = request.env["REQUEST_URI"].sub("/#{params[:service]}/", '')
  uri = URI.parse("#{@service.url}#{path.to_s}")
  header_opts = cache_check(uri)
  if header_opts["body"] 
    cached_content_type = header_opts.delete("Content-Type")  
    z = Zlib::Inflate.new     
    cached_body = z.inflate(header_opts.delete("body"))
    z.close
  end
  header_opts['User-Agent'] = 'JangleR Core 1.0'
  client = RFuzz::HttpClient.new(uri.host, uri.port)
  qry = "#{uri.path}"
  qry << "?#{uri.query}" if uri.query  
  res = client.get(qry,
    :head=>{'accept'=>'application/json', 'X-Connector-Base'=>"#{CONFIG["server"]["base_url"]}#{params[:service]}"}.merge(header_opts))

  status = res.http_status
  message = res.http_reason
  if status == "301" || status == "302"
    redirect "/#{params[:service]}#{res["LOCATION"]}"
  end
  
  unless status == "200" || status == "304"
    throw :halt, [status.to_i, message]
  end

  if status == "304"
    header 'Content-Type' => cached_content_type
    output = cached_body
  else   
    @connector_response = JSON.parse(res.http_body)
    if @connector_response["type"] == "explain"
      header 'Content-Type' => 'application/opensearchdescription+xml'
    else
      header 'Content-Type' => 'application/atom+xml'  
    end
    output = builder get_template(@connector_response["type"])
    if @connector_response["stylesheets"]
      @connector_response["stylesheets"].each do | xsl |
        output = transform_document(xsl, output)
      end
    end
    cache_response(uri,res,@connector_response["type"],output)
  end
  output
end

before do
  @cache = PStore.new("#{CONFIG["cache_location"]}/connector_responses.pstore")
end

helpers do
  # Basically this prevents us from worrying about double slashes at the start of a URI path
  def uri_from_path(path)
    CONFIG['server']['base_url'].chomp("/")+path      
  end
  
  def cache_check(uri)
    cache = {}
    @cache.transaction do
      return cache unless @cache[uri]
      return {} if @cache[uri]["Last-Modified"].nil?
      cache["If-Modified-Since"] = @cache[uri]["Last-Modified"]
      cache["If-None-Match"] = @cache[uri]["ETag"]
      cache["body"] = @cache[uri]["body"]
      cache["Content-Type"] = @cache[uri]["Content-Type"]
    end
    return cache
  end
  
  def cache_response(uri,response,document_type,body)
    @cache.transaction do      
      @cache[uri] = {"Last-Modified"=>response['LAST_MODIFIED'], "ETag"=>response['ETAG']}
      if document_type == "explain"
        @cache[uri]["Content-Type"] = "application/opensearchdescription+xml"
      else
        @cache[uri]["Content-Type"] = "application/atom+xml"
      end
      z = Zlib::Deflate.new(9)
      @cache[uri]["body"] = z.deflate(body,Zlib::FINISH)
      z.close
    end
  end
  
  def response_broker(json)
    {'feed'=>Jangle::FeedResponse, 'services'=>Jangle::ServiceResponse, 
      'explain'=>Jangle::OpenSearchDescription}.each do |mime, response_type|
      if type == json["type"]
        return response_type.new_from_response(json) 
      end
    end
  end  
  def get_template(type)
    map = {"services"=>:service_document,"feed"=>:feed,"search"=>:feed,"explain"=>:explain}
    return map[type]
  end
  def transform_document(stylesheet, document)
    xslt = XML::XSLT.new()
    cache = XslCache.create
    xslt.xml = document
    xslt.xsl = cache.xsl(stylesheet)
    return xslt.serve().sub(/\<\?[^\?]*\?\>/,"")    
  end
  
  # The connector response now just sends "offset" and "totalResults", pushing the responsibility of paging to Jangle core
  def pager(response)
    this_request = URI.parse("#{CONFIG["server"]["base_url"].sub(/\/$/,'')}#{request.env["REQUEST_URI"]}")
    pages = {:self=>this_request.to_s}
    query = CGI.parse((this_request.query||""))
    if response["totalResults"].to_i > response["data"].length
      query["offset"] = 0
      this_request.query = params_to_querystring(query)
      pages[:first] = this_request.to_s      
    end
    if response["totalResults"].to_i > (response["offset"].to_i+response["data"].length)
      query["offset"] = (response["offset"].to_i+response["data"].length)
      this_request.query = params_to_querystring(query)
      pages[:next] = this_request.to_s
      query["offset"] = response["totalResults"].to_i.divmod(response["data"].length).first*response["data"].length
      this_request.query = params_to_querystring(query)
      pages[:last] = this_request.to_s
    end
    unless response["offset"].to_i == 0
      query["offset"] = (response["offset"].to_i-response["data"].length)
      query["offset"] = 0 if query["offset"].to_i < 0
      this_request.query = params_to_querystring(query)
      pages[:previous] = this_request.to_s
    end    
    return pages
  end
  
  def params_to_querystring(hash)
    query = []
    hash.each do | key, val|
      if val.is_a?(Array)
        val.each do | v |
          query << "#{key}=#{v}"
        end
      else
        query << "#{key}=#{val}"
      end
    end
    CGI.escape(query.join("&"))
  end
  
end

class XslCache
  private_class_method :new
  @@xsl_cache = nil
  @@cache = {}
  def self.create
    @@xsl_cache = new unless @@xsl_cache
    @@xsl_cache
  end
  def xsl(uri)
    self.add_xsl(uri) unless @@cache[uri]
    return @@cache[uri]
  end
  
  def add_xsl(uri)
    @@cache[uri] = open(uri).read
  end
end

module Jangle
  # Generic Service proxy object.
  class Service
    attr_accessor :title, :url, :description
    # Builds a new object from the yaml config
    def self.new_from_yaml(t, block)
      s = self.new
      s.title = t
      s.url = block["connector_url"]
      s.description = block["description"]
      #s.get_collections    
      s
    end
    # Grabs the collections hash from the connector
    # Some cacheing would probably useful here.
    def get_service_document
      svcs = Net::HTTP.get(URI.parse("#{self.url}services/"))
      return JSON.parse(svcs)    
    end
  end

  class Response
    attr_reader :document, :content_type
    attr_accessor :extensions
  end

  class FeedResponse < Response  
    attr_accessor :links, :entries, :offset, :total_results
    def initialize
      super 
      @document = :feed
      @content_type = 'application/atom+xml'
    end

    def self.new_from_response(json)
      feed = self.new
      @content_type = json["type"]
      feed.links = json["links"]
      feed.entries = json["data"]
      feed.extensions = json["extensions"]
      feed.offset = (json["offset"]||0).to_i
      feed.total_results = (json["totalResults"]||0).to_i
      return feed
    end
  end

  class ServiceResponse < Response
    attr_accessor :workspaces
    def initialize
      super
      @document = :service_document
      @content_type = 'application/atomsvc+xml'
      @workspaces = {}
    end
  
    def self.new_from_response(json)
      atomsvc = self.new
      
      json["workspaces"].each do | ws, coll|
        atomsvc.workspaces[ws] = {"collections"=>{}}
        coll.each do | col, data |
          atomsvc.workspaces[ws]["collections"] = data
        end
      end
      return atomsvc
    end
  
  end

  class OpenSearchDescription < Response
    attr_accessor :shortname, :longname, :description, :tags, :image, :query, :developer,
      :attribution, :syndicationright, :adultcontent, :language, :inputencoding, :outputencoding, :url  
  
    def initialize
      super
      @document = :opensearchdescription
      @content_type = 'application/opensearchdescription+xml'
    end
    def self.new_from_response(json)
      desc = self.new
      json.each do | key, val |
        next if key.match(/^type$|^request$/)
        desc.instance_variable_set("@#{key}", val)
      end
      desc.extensions = json["extensions"]
      return desc
    end  
  end
end
