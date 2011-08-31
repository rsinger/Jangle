require 'rubygems'
require 'sinatra'
require 'net/http'
require 'yaml'
require 'rexml/document'
require 'builder'

configure do 
  CONFIG = YAML.load_file('config.yml')
end

get "/:service/:resource/" do
  if params[:id] && params[:format]
    response = get_object(params)
  elsif params[:id]
    response = get_formats(params)
  else
    response = get_all_formats()
  end  
  response
end

helpers do
  def get_object(params)
    uri = params[:id]
    uri << "?#{CONFIG['jangle_server']['format_argument']}=#{params[:format]}" unless params[:format] == "__default__"
    resp = http_get(uri)
    doc = REXML::Document.new resp
    content = doc.elements["/feed/entry/content"]
    header 'Content-Type' => content.attributes['type']
    return content.to_a.join("")
  end
  
  def get_formats(params)
    resp = http_get(params[:id])
    doc = REXML::Document.new resp
    formats = {}
    content_type,identifier = get_entry_format(doc.elements["/feed/entry"])
    formats[identifier] = {:content_type=>content_type, :format=>"__default__"}
    alt_fmt = nil
    get_alt_formats(doc.elements["/feed/entry"]).each do | fmt |      
      formats[fmt[:identifier]] = {:content_type=>fmt[:content_type],:format=>fmt[:format]}      
    end
    header 'Content-Type' => 'application/xml'
    return build_formats_document(formats, params[:id])
  end
  
  def get_all_formats
    resp = http_get("#{CONFIG["jangle_server"]["base_uri"]}#{params[:service]}/#{params[:resource]}/")
    doc = REXML::Document.new resp
    formats = {}
    content_type,identifier = get_entry_format(doc.elements["/feed/entry"])
    formats[identifier] = {:content_type=>content_type, :format=>"__default__"}
    alt_fmt = nil
    get_alt_formats(doc.elements["/feed/entry"]).each do | fmt |      
      formats[fmt[:identifier]] = {:content_type=>fmt[:content_type],:format=>fmt[:format]}      
    end
    header 'Content-Type' => 'application/xml'
    return build_formats_document(formats, params[:id])    
  end
  
  def get_entry_format(entry)
    identifier = nil
    if self_link = entry.elements["./link[@jangle:format]"]
      identifier = self_link.attributes["jangle:format"]
    end
    content_type = entry.elements["content"].attributes["type"]
    return [content_type, identifier]
  end
  
  def get_alt_formats(entry)
    alts = []
    entry.each_element("./link") do | link |      
      if link.attributes["rel"] =~ /http:\/\/jangle\.org\/vocab\/formats/
        alt = {}
        puts link.to_s
        loc = link.attributes["href"]
        puts loc
        a = http_get(loc)
        doc_a = REXML::Document.new a
        alt[:content_type], alt[:identifier] = get_entry_format(doc_a.elements["feed/entry"])
        u = URI.parse(loc)
        c = CGI.parse(u.query)
        alt[:format] = c[CONFIG["jangle_server"]["format_argument"]][0]
        alts << alt
      end
    end
    alts
  end          
  
  def http_get(loc)
    uri = URI.parse(loc)
    res = Net::HTTP.start(uri.host, uri.port) do |http|
      qry = "#{uri.path}"
      qry << "?#{uri.query}" if uri.query    
      http.get(qry, {'accept'=>'application/atom+xml'})
    end

    if res.class == Net::HTTPFound
      redirect "/#{params[:service]}#{res.header['location']}"
    end

    status = res.code
    message = res.message

    unless status == "200" || status == "304"
      throw :halt, [status.to_i, message]
    end    
    
    return res.body
  end
  
  def build_formats_document(formats, id=nil)
    x = Builder::XmlMarkup.new
    opts = {}
    opts[:id] = id if id
    x.formats(opts) do | fmts |
      formats.keys.each do | fmt |
        fmts.format(:name=>formats[fmt][:format],:type=>formats[fmt][:content_type],:docs=>fmt)
      end
    end
    x.target!
  end
end