require 'rubygems'
require 'sinatra'
require 'yaml'
require 'oai'
require 'feed_tools'

# Load our configuration file.
CONFIG = YAML.load_file('config.yml')

get '/:service/OAI/bibliographic' do
  header 'Content-Type' => 'application/xml'
  @oai_pmh = OAIResponse.new(params, request)
  builder @oai_pmh.document
end

get '/availability/' do
  header 'Content-Type' => 'application/xml'
  ids = params[:id].split(/\s/)
  @records = {}
  ids.each do | id |
    items = get_availability(id)
    puts items.inspect
    items.each do | key, val |
      @records[key] = [] unless @records[key]
      @records[key] += val
    end
  end

  builder :availability
end

get '/goto/' do
  params[:id] = params[:uri] if params[:uri]
  feed = FeedTools::Feed.open(params[:id])
  entry = feed.entries.first
  throw :halt, ["404", "Not Found"] if feed.entries.empty?
    
  entry.links.each do | lnk |
    if lnk.rel == "alternate" && lnk.type == "text/html"
      redirect lnk.href
    end
  end
  
end

helpers do 
  def get_availability(uri)
    uri = "#{uri}/items/" if uri =~ /\/resources\/[0-9]*$/
    records = {}
    response = FeedTools::Feed.open(uri)
    response.entries.each do | entry |
      puts entry.inspect
      bib_id = nil
      entry.links.each do | link |
        if link.rel == "related" and link.href =~ /\/resources\/[0-9]*$/
          bib_id = link.href
          records[bib_id] = [] unless records[bib_id]
        end
      end
      
      item_avail = {:item_id => entry.id, :status=>entry.title, :message=>entry.summary}
      item_avail[:message] = entry.summary if entry.find_node('summary')
      if content = entry.xml_document.elements['entry'].elements['content']
        if date_available = content.elements["dlf:record/dlf:items/dlf:item/dlf:simpleavailability/dlf:dateavailable", 
          {"dlf"=>"http://onlinebooks.library.upenn.edu/schemas/dlf/1.0/"}]
          item_avail[:date_available] =  date_available.get_text.value
        end
      end
      records[bib_id] << item_avail      
    end
    return records
  end
end

class OAIResponse
  attr_reader :verb, :metadata, :repo_name, :repo_url, :repo_admin, :description, 
    :document, :metadata_prefix, :from, :until, :set, :resumption_token, :identifier
  
  def initialize(params, request)
    @verb = params[:verb]
    @repo_name = "#{params[:service]} OAI-PMH Repository Server"
    @repo_host = CONFIG["repo_hostname"]
    @repo_url = "http://" + @repo_host + request.env["REQUEST_PATH"]
    @repo_admin = "rossfsinger@gmail.com"
    @jangle_url = CONFIG["jangle_url"]
    @description = {}
    if params[:resumptionToken]      
      params = HashWithIndifferentAccess.new(CGI.parse(Base64.decode64(params[:resumptionToken])))
    end
    self.send(@verb.downcase.to_sym, params)
  end
  
  def identify(params)
    @document = :oai_identify
    uri = URI.parse(@repo_url)
    feed =  get_feed(params)
    feed.links.each do | link |
      if link.rel == "last"
        feed = FeedTools::Feed.open(link.href)
        break
      end
    end
    @metadata = feed.entries
  end
  
  def listrecords(params)
    @document = :oai_list
    @metadata_prefix = params[:metadataPrefix]
    feed = get_feed(params)
    @metadata = feed.entries
    self.check_paging(feed, params)
  end
  
  alias_method :listidentifiers, :listrecords
  
  def getrecord(params)
    @document = :oai_list
    @metadata_prefix = params[:metadataPrefix]
    @identifier = params[:identifier]

    feed = FeedTools::Feed.open("#{params[:identifier]}?record_type=#{@metadata_prefix}")
    @metadata = feed.entries
  end
  
  def listmetadataformats(params)
    @document = :oai_metadata_formats
    @identifier = params[:identifier]    
  end
  
  def listsets(params)
    @document = :oai_list_sets
    feed = self.get_feed(params)
    @metadata = feed.entries
    self.check_paging(feed, params)
  end
  
  def check_paging(feed, params)
    feed.links.each do | link |
      if link.rel == "next"
        uri = URI.parse(link.href)
        cgi_vars = CGI.parse(uri.query)
        params["page"] = cgi_vars["page"].first unless cgi_vars["page"].empty?
        restokray = []
        params.each do | key, val |
          restokray << "#{key}=#{val}" 
        end
        @resumption_token = Base64.b64encode(restokray.join("&"))
      end
    end    
  end
    
  def get_feed(params)
    uri = "#{@jangle_url}#{params[:service]}/"
    # Sets correspond to Jangle collections.  Maybe not perfectly, so this may be tweaked a bit.
    if @set = params[:set]
      uri << "collections/#{params[:set]}"
    elsif params[:verb] == "ListSets"
      uri << "collections/"
    else
      uri << "resources/"      
    end
    if params[:from] or params[:until]
      uri << "/" unless uri.last == "/"
      uri << "search/"
    end
    unless params[:verb] == "ListSets" or params[:verb] == "Identify"
      uri << "?format=#{params[:metadataPrefix]}"
    end
    {:from, :until}.each do | point |  
              
      if params[point]
        uri << "&#{point.to_s}=#{params[point]}"   
        self.instance_variable_set("@#{point.to_s}", params[point])    
      end
    end
    uri << "&offset=#{params[:offset]}" if params[:offset]
    puts uri
    return FeedTools::Feed.open(uri)
  end
end