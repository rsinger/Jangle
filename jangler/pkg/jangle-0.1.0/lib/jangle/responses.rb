module Jangle
  # This is the object that gets serialized as JSON to return to the core  
  class ResponseMessage
    require 'date'
    def initialize(uri)
      @message = {"request"=>"#{CONFIG[:base_uri].sub(/\/$/,'')}#{uri}"}
    end
  
    def message    
      @message.to_json
    end
    
    def link(rel, uri, type="application/atom+xml")
      @message[:links] = {} unless @message[:links]
      @message[:links][rel] = {:href=>uri, :type=>type}
    end
  
    def register_extension(prefix, namespace)
      @message[:extensions] ||={}
      @message[:extensions][prefix] = {:namespace=>namespace, :data=>[]}
    end
  
    def extension_data(extension, data)
      @message[:extensions][extension][:data] << data
    end  
  end
  
  class ServiceResponseMessage < ResponseMessage
    def initialize(uri)
      super(uri)
      @message["type"] = "services"
      @message["title"] = CONFIG["global_options"]["service_name"]
      entities = {}
      categories = {}
      entity_path_map = {"actors"=>"Actor","resources"=>"Resource","items"=>"Item","collections"=>"Collection"}
      CONFIG["resources"].each do | entity, settings |
        entities[entity_path_map[entity]] = {"title"=>settings["title"]||entity}
        entities[entity_path_map[entity]]["accept"] = settings["accept"] if settings["accept"]
        entities[entity_path_map[entity]]["path"] = "/#{entity}/"
        if settings["search"]
          entities[entity_path_map[entity]]["searchable"] = "/#{entity}/search/description/"
        else
          entities[entity_path_map[entity]]["searchable"] = false
        end
        if settings["categories"]
          settings["categories"].each do | category |
            entities[entity_path_map[entity]]["categories"] ||=[] << category        
            categories[category] = {"scheme"=>CONFIG["categories"][category]["scheme"]}
            categories[category]["label"] = CONFIG["categories"][category]["label"] if CONFIG["categories"][category]["label"] 
          end        
        end
      end
      @message.merge!({"type"=>"services", "title"=>CONFIG["global_options"]["service_name"], "entities"=>entities, "categories"=>categories})
    end    
  end

  class FeedResponseMessage < ResponseMessage
    def initialize(uri)
      super(uri)
      u = URI.parse(uri)
      offset = 0
      if u.query
        p = CGI.parse(u.query)
        offset = p["offset"][0].to_i if p["offset"] and not p["offset"].empty?
      end
      @message.merge!({:type=>'feed', :time=>Time.now.xmlschema, :data=>[], :totalResults=>0, :offset=>offset})
    end
  
    def add_record_type(key)
      @message[:formats] ||=[] << CONFIG["record_types"][key]["uri"]
    end
    
    def record_types
      return @message[:formats]
    end
  
    def total_results=(int)
      @message[:totalResults] = int
    end
    
    def offset=(int)
      @message[:offset] = int
    end
  
    def add_to_total(int)
      @message[:totalResults] ||=0
      @message[:totalResults] += int
    end
    
    def add_stylesheet(url)
      @message[:stylesheets] ||=[] << url
    end
    
    def add_alternate_record_type(format)
      @message["alternate_formats"] ||={}
      u = URI.parse(@message["request"])
      if u.query
        if u.query.match(/format=/)
          u.query.sub!(/format=[^&]*/,"format=#{format}")
        else
          u.query << "&format=#{format}"
        end
        uri = u.to_s        
      else
        uri = "#{@message['request']}?format=#{format}"
      end
      @message["alternate_formats"][CONFIG["record_types"][format]["uri"]] = uri
    end
    
    def add_category(category)
      @message[:categories] ||=[] << category
    end    
  
    def <<(entry)
      @message[:data] << entry
    end  
  end
  
  class SearchResponseMessage < FeedResponseMessage
    def initialize(uri)
      super(uri)
      @message[:type] = "search"
    end
  end

  class FeedEntry
    attr_accessor :title, :content, :content_type, :description, :created, :stylesheet, :categories, :author
    attr_reader :id, :updated, :links, :alternate_formats, :relationships, :format
  
    def initialize(id, updated=Time.now.xmlschema)
      @id = "#{CONFIG[:base_uri].sub(/\/$/,'')}#{id}"
      @updated = updated
      @alternate_formats = {}
      @relationships = {}
    end
  
    def add_rel(href, type='application/atom+xml', title=nil)
      attrs = self.set_link_attrs(href, type, title)
      self.add_link("related", attrs)
    end
  
    def add_relationship(entity, id=nil, title=nil, format=nil)
      relationship = "http://jangle.org/rel/related##{entity.capitalize}"
      if id
        href = "#{CONFIG[:base_uri]}/#{entity.downcase}s/#{id}"
      else
        href = "#{@id}/#{entity.downcase}s/"
      end
      href << "?format=#{format}" if format
      @relationships[relationship] = href
      type = "application/atom+xml"
      attrs = self.set_link_attrs(href, type, title)
      self.add_rel(href, type, title)
    end
  
    def add_alternate_record_type(format)
      u = URI.parse(self.id)
      if u.query
        if u.query.match(/format=/)
          u.query.sub!(/format=[^&]*/,"format=#{format}")
        else
          u.query << "&format=#{format}"
        end
        uri = u.to_s        
      else
        uri = "#{self.id}?format=#{format}"
      end
      @alternate_formats[CONFIG["record_types"][format]["uri"]] = uri
    end
    
    def record_type=(key)
      @format = CONFIG["record_types"][key]["uri"]
    end  
    def set_link_attrs(href, type, title)
      attrs = {:href=>href, :type=>type}
      attrs[:title] =  title if title
      return attrs
    end
    
    def add_category(category)
      @categories ||=[] << category
    end
  
    def add_link(relationship, attributes)
      @links ||={}
      (@links[relationship] ||=[]) << attributes
    end
  
    def add_alt(href, type='application/atom+xml', title=nil)
      attrs = self.set_link_attrs(href, type, title)
      self.add_link("alternate", attrs)
    end 
  
    def to_hash
      hash = {:id=>@id, :updated=>@updated}
      [:content, :content_type, :title, :description, :created, :stylesheet, :categories, :author,
        :links, :alternate_formats, :relationships, :format].each do | attr |
        hash[attr] = self.send(attr) if self.send(attr)        
      end
      hash
    end
  end

  class OpenSearchDescriptionResponseMessage < ResponseMessage
    attr_accessor :shortname, :longname, :description, :tags, :image, :query, :developer, :contact,
      :attribution, :syndicationright, :adultcontent, :language, :inputencoding, :outputencoding, :template  
    def initialize(uri)
      super(uri)
      @message[:type] = "explain"
      @url = []
    end
  
    def message
      @message.merge(:shortname=>@shortname, :longname=>@longname, :description=>@description, :tags=>@tags,
        :image=>@image, :query=>@query, :developer=>@developer, :attribution=>@attribution, 
        :syndicationright=>@syndicationright, :adultcontent=>@adultcontent, :language=>@language,
        :inputencoding=>@inputencoding, :outputencoding=>@outputencoding, :template=>@template).to_json
    end
  
    def self.new_from_config(uri, resource)
      resp = self.new(uri)

      config = CONFIG["resources"][resource]
      resp.shortname = config["search"]["shortname"]||config["title"]
      resp.longname = config["search"]["longname"] if config["search"]["longname"]
      resp.description = config["search"]["description"] if config["search"]["description"]
      resp.contact = config["search"]["contact"] if config["search"]["contact"]
      resp.tags = config["search"]["tags"] if config["search"]["tags"]
      if config["search"]["image"]
        resp.image = {:location=>config["search"]["image"]["location"]}
        resp.image["height"] = config["search"]["image"]["height"] if config["search"]["image"]["height"]
        resp.image["widgth"] = config["search"]["image"]["width"] if config["search"]["image"]["width"]
        resp.image["type"] = config["search"]["image"]["type"] if config["search"]["image"]["type"]
      end
      if config["search"]["query"] or config["search"]["indexes"]
        resp.query = {}
        resp.query["example"] = config["search"]["query"] if config["search"]["query"]
        if config["search"]["indexes"]
          resp.query["context-sets"] = []
          context_sets = {}
          config["search"]["indexes"].each do | index |
            name,idx = index.split(".")
            unless context_sets[name]
              context_sets[name] = {"identifier"=>CONFIG["context-sets"][name]["identifier"],"indexes"=>[]}
            end
            context_sets[name]["indexes"] << idx
          end
          context_sets.each_key do | name |
            resp.query["context-sets"] << {"name"=>name,"identifier"=>context_sets[name]["identifier"],"indexes"=>context_sets[name]["indexes"]}
          end
        end
      end
      resp.developer = config["search"]["developer"] if config["search"]["developer"]
      resp.attribution = config["search"]["attribution"] if config["search"]["attribution"]
      resp.syndicationright = config["search"]["syndicationright"] if config["search"]["syndicationright"]
      resp.adultcontent = config["search"]["adultcontent"] if config["search"]["adultcontent"]
      resp.language = config["search"]["language"] if config["search"]["language"]
      resp.inputencoding = config["search"]["inputencoding"] if config["search"]["inputencoding"]
      resp.outputencoding = config["search"]["outputencoding"] if config["search"]["outputencoding"]
      url = "/#{resource}/search/?"
      if config["search"]["parameters"]
        params = []
        config["search"]["parameters"].each do | param, value |
          unless value.match(/^searchTerms|^count|^startPage|^startIndex|^language|^inputEncoding|^outputEncoding/)
            resp.register_extension("jangle", "http://jangle.org/opensearch/") unless resp.instance_variable_get("@message")["jangle"]          
            params << "#{param}={jangle:#{value}}"
          else
            params << "#{param}={#{value}}"
          end
        end
        url << params.join("&")
      else
        url << "query={searchTerms}"
      end
      resp.template = url
      return resp
    end
  end
end