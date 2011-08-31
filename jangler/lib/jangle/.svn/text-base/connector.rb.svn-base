require 'rubygems'
require 'sinatra'
require 'yaml'
require 'jangle/responses'
require 'md5'

configure do
  # Load the connector configuration
  CONFIG = YAML.load_file('config.yml')
  CONFIG["last-restart"] = Time.now.xmlschema
  CONFIG["ETag"] = MD5.md5(CONFIG["last-modified"]).to_s
  #####
  # WARNING!  SUPER HACK
  # Allow dots in path param values
  Sinatra::Event.send(:remove_const, "URI_CHAR")
  Sinatra::Event.const_set("URI_CHAR", '[^/?:&#]')
end  

# Defines the entities available through this connector.  The "accept" key indicates what kind of mime-types can be 
# sent in an Atom document to the server.
get '/services' do
  redirect '/services/'
end

get '/services/' do  

  do_cache_if_requested(CONFIG["last-restart"],CONFIG["ETag"])

  mesg = Jangle::ServiceResponseMessage.new(request.env["REQUEST_URI"])
  mesg.message
end


# Redirect to a URI with a trailing slash
get '/:resource' do
  redirect "/#{params[:resource]}/"
end

# Gets all resources at the given URI
get '/:resource/' do
  unless CONFIG["resources"].keys.index(params[:resource])
    throw :halt, [404, "Not Found"]
  end
  
  unless CONFIG["resources"][params[:resource]]["options"] && CONFIG["resources"][params[:resource]]["options"].index('get') 
    throw :halt, [405, "Method not allowed"]
  end
  
  object_class = Kernel.const_get(params[:resource].sub(/s$/,'').capitalize)
  caching_check(object_class, :all, (params[:offset]||0),(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0]))
  object_class.feed(:all, (params[:offset]||0),(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0]), request.env["REQUEST_URI"])
end

# Gets all resources at the given URI
get '/:resource/-/:category/' do
  unless CONFIG["resources"].keys.index(params[:resource]) && CONFIG["resources"][params[:resource]]["categories"].index(params[:category])
    throw :halt, [404, "Not Found"]
  end
  
  unless CONFIG["resources"][params[:resource]]["options"] && CONFIG["resources"][params[:resource]]["options"].index('get') 
    throw :halt, [405, "Method not allowed"]
  end
  
  object_class = Kernel.const_get(params[:resource].sub(/s$/,'').capitalize)
  caching_check(object_class, :all, 
   (params[:offset]||0),(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0]),params[:category])
  object_class.feed(:all,(params[:offset]||0),(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0]),
   request.env["REQUEST_URI"], params[:category])
end

get '/:resource/search' do
  redirect "/#{params[:resource]}/search/"
end

get '/:resource/search/' do
  if CONFIG["resources"][params[:resource]]["search"] == false
    throw :halt, [404, "Not Found"]
  end
  redirect "/#{params[:resource]}/search/description/" if request.env["QUERY_STRING"].empty?
  object_class = Kernel.const_get(params[:resource].sub(/s$/,'').capitalize)
  count, objects = object_class.search(params[:query],(params[:offset]||0),(params[:count]||object_class.limit),
   (params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0]))
  # Create the response
  mesg = Jangle::SearchResponseMessage.new(request.env["REQUEST_URI"])  
  mesg.total_results = count
  mesg.offset = params[:offset]||0

  objects.each do | object |
    mesg << object.entry(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0])
    if mesg.record_type.nil? || !mesg.record_type.index(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0])
      mesg.add_record_type(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0])
    end
  end
  mesg.message  
end

get '/:resource/search/description/' do
  mesg = Jangle::OpenSearchDescriptionResponseMessage.new_from_config(request.env["REQUEST_URI"], params[:resource])
  mesg.message
end
  
# Get an resource record or specific set of resource records by Id
get '/:resource/:id' do
  unless CONFIG["resources"].keys.index(params[:resource])      
    throw :halt, [404, "Not Found"]
  end
  
  unless CONFIG["resources"][params[:resource]]["options"] && CONFIG["resources"][params[:resource]]["options"].index('get') 
    throw :halt, [405, "Method not allowed"]
  end
  
  # This assumes that we have a list or a range
  if params[:id].match(/[,;-]/)
    params[:id] = id_translate(params[:id])    
  end
  object_class = Kernel.const_get(params[:resource].sub(/s$/,'').capitalize)
  caching_check(object_class, params[:id], (params[:offset]||0),(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0]))
  object_class.feed(params[:id], (params[:offset]||0),(params[:format]||CONFIG["resources"][params[:resource]]["record_types"][0]), request.env["REQUEST_URI"])  
end


get '/:resource/:id/:service' do
  redirect "/#{params[:resource]}/#{params[:id]}/#{params[:service]}/"
end

# Get the Items associated with a particular Resource
get '/:resource/:id/:service/' do
  unless CONFIG["resources"].keys.index(params[:resource])    
    throw :halt, [404, "Not Found"]
  end
  unless CONFIG["resources"][params[:resource]]["services"] && CONFIG["resources"][params[:resource]]["services"].index(params[:service])
    throw :halt, [404, "Not Found"]
  end  
  
  unless CONFIG["resources"][params[:resource]]["options"] && CONFIG["resources"][params[:resource]]["options"].index('get') 
    throw :halt, [405, "Method not allowed"]
  end
  mesg = Jangle::FeedResponseMessage.new(request.env["REQUEST_URI"])
  # This assumes that we have a list or a range
  if params[:id].match(/[,;-]/)
    params[:id] = id_translate(params[:id])        
  end
  mesg.offset = params[:offset]||0
  begin
    object_class = Kernel.const_get(params[:resource].sub(/s$/,'').capitalize)    
    object = object_class.find_by_identifier(params[:id])
    unless object.is_a?(Array)
      cond = pager(params, mesg, object.send(params[:service]))
      mesg.total_results = object.send("count_#{params[:service]}")
      services = object.send(params[:service], cond)
      services.each do | svc |
        mesg << svc.entry(params[:format]||CONFIG["resources"][params[:service]]["record_types"][0])
        fmt = params[:format]||CONFIG["resources"][params[:service]]["record_types"][0]
        if mesg.record_types.nil?
          mesg.add_record_type(fmt)
        elsif !mesg.record_types.index(fmt)
            mesg.add_record_type(fmt)
        end    
      end
    else      
      mesg.total_results = 0
      object.each do | o |
        o.send(params[:service]).each do | svc |
          mesg << svc.entry(params[:format]||CONFIG["resources"][params[:service]]["record_types"][0])
          fmt = params[:format]||CONFIG["resources"][params[:service]]["record_types"][0]
          if mesg.record_types.nil? || !mesg.record_types.index(fmt)
            mesg.add_record_type(fmt)
          end          
          mesg.add_to_total(1)
        end        
      end
    end
  rescue
    throw :halt, [404, "Not Found"]
  end
  mesg.message  
end

# All of this takes place before any of the above routing
before do
  # There seems some to be some ambiguity around which is the proper mime-type to send JSON as.
  # This will honor text/json or application/json.  For testing purposes, the default is text/html.
  header 'Content-Type' => 'text/json' if request.env['HTTP_ACCEPT'] == "text/json"
  header 'Content-Type' => 'application/json' if request.env['HTTP_ACCEPT'] == "application/json"
  CONFIG[:base_uri] = (request.env["HTTP_X_CONNECTOR_BASE"] || "")
end

helpers do 
  def id_translate(id)
    #if the request is simple (integer, list or nil) return that
    return nil if id.empty?
    return id if id.match(/^\d+$/)
    return id.split(/[,;]/) if id.match(/^(\d+[,;]?)*$/)
    #if the request includes a range (x-y), translate that into an array
    ids = []
    id.split(/[,;]/).each do | i |
      if i.match(/^\d+$/)
        ids << i
      else
        start_rng, end_rng = i.split("-")
        (start_rng..end_rng).each { | r | ids << r }          
      end
    end
    return ids
  end
  
  def hash2query_str(hash)
    query = []
    hash.each do | key, val |
      query << "#{key}=#{val}"
    end
    return query.join("&")
  end  
  
  def caching_check(klass,id, offset, record_format, category=nil)
    return unless klass.respond_to?(:cache)
    tstamp,to_hash=klass.cache(id, offset, record_format, category)
    do_cache_if_requested(tstamp,MD5.md5(to_hash).to_s)
  end
  
  def do_cache_if_requested(last_mod, etag)
    modded_etag = CONFIG["last-restart"] + etag
    if request.env["HTTP_IF_MODIFIED_SINCE"]
      if_mod_since = Time.parse(request.env["HTTP_IF_MODIFIED_SINCE"])
    else
      if_mod_since = nil
    end
    if request.env["HTTP_IF_MODIFIED_SINCE"] || request.env["HTTP_IF_NONE_MATCH"]
      if if_mod_since ==  last_mod && request.env["HTTP_IF_NONE_MATCH"] == modded_etag
        throw :halt, [304]
      elsif if_mod_since == last_mod && !request.env["HTTP_IF_NONE_MATCH"]
        throw :halt, [304]
      elsif request.env["HTTP_IF_NONE_MATCH"] == modded_etag && !request.env["HTTP_IF_MODIFIED_SINCE"]
        throw :halt, [304]
      end
    end
    header 'Last-Modified' => last_mod.asctime
    header 'ETag' => modded_etag
  end    
  
  def pager(params, mesg, paged_class)
    # Set the paging links in the response
    if params[:service] && CONFIG["resources"][params[:service]] && CONFIG["resources"][params[:service]]["maximum_results"]
      limit = CONFIG["resources"][params[:service]]["maximum_results"]
    elsif !params[:service] && params[:resource ]&& CONFIG["resources"][params[:resource]] && CONFIG["resources"][params[:resource]]["maximum_results"]
      limit = CONFIG["resources"][params[:resource]]["maximum_results"]
    else
      limit = CONFIG["global_options"]["maximum_results"]
    end
    cond = {:limit=>limit}
    cond[:offset] = (params[:offset].to_i || 0)
    return cond   
  end

end
