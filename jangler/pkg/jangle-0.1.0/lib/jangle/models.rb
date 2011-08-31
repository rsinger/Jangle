module Jangle
  class Entity
    def self.feed(id,offset,record_format,uri,category=nil)
      feed = Jangle::FeedResponseMessage.new(uri)
      feed.offset = offset
      feed.add_record_type(record_format)
      if self.check_stylesheet(record_format, "feed")
        feed.add_stylesheet(self.check_stylesheet(record_format, "feed"))
      end
      CONFIG["resources"]["#{self.identify.downcase}s"]["record_types"].each do | alt_format |
        next if alt_format == record_format
        feed.add_alternate_record_type(alt_format)
      end      
      unless category  
        if id == :all          
          resources = self.all(offset,record_format)          
          feed.total_results=self.count
        else
          resources = self.find_by_identifier(id, record_format)
          feed.total_results = resources.length
        end
      else
        resources = self.category(category,offset,record_format)
        feed.total_results = self.category_count(category)
        feed.add_category(category)
      end
      resources.each do | resource |
        feed << resource.entry(record_format)
      end
      feed.message
    end
    
    def self.limit
      if CONFIG["resources"]["#{self.identify.downcase}s"]["maximum_results"]
        limit = CONFIG["resources"]["#{self.identify.downcase}s"]["maximum_results"]
      else
        limit = CONFIG["global_options"]["maximum_results"]
      end
      limit
    end    
    
    def self.identify
      return self.name.split("::").last
    end
    
    def self.check_stylesheet(format, level)
      return false unless CONFIG["record_types"][format]["stylesheets"] && CONFIG["record_types"][format]["stylesheets"][level]
      CONFIG["record_types"][format]["stylesheets"][level]
    end
    
    def entry(format, mixed_feed=false)
      uri = "/#{self.class.identify.downcase}s/#{self.identifier}"
      uri << "?format=#{format}" unless format == CONFIG["resources"]["#{self.class.identify.downcase}s"]["record_types"][0]
      entry = Jangle::FeedEntry.new(uri, self.last_modified)
      if CONFIG["record_types"][format]["method"]
        method = CONFIG["record_types"][format]["method"]
      else
        method = format
      end
      entry.record_type=format
      entry.content = self.send(method.to_sym)
      entry.content_type = CONFIG["record_types"][format]["content-type"]
      CONFIG["resources"]["#{self.class.identify.downcase}s"]["record_types"].each do | alt_format |
        next if alt_format == format
        entry.add_alternate_record_type(alt_format)
      end
      if (mixed_feed && self.class.check_stylesheet(format, "item")) || (self.class.check_stylesheet(format, "item") && !self.class.check_stylesheet(format, "feed"))
        entry.add_stylesheet(self.class.check_stylesheet(format, "item"))
      end
      entry.title = self.title
      entry.created = self.created if self.respond_to?("created")
      entry.author = self.author if self.respond_to?("author")
      entry.description = self.description if self.respond_to?("description")
      if self.respond_to?("categories")
        entry.categories = self.categories if self.categories
      end
      CONFIG["resources"]["#{self.class.identify.downcase}s"]["services"].each do | service |
        entry.add_relationship(service.sub(/s$/,'').capitalize) if self.send("#{service}?")
      end
      if url = CONFIG["resources"]["#{self.class.identify.downcase}s"]["url_template"]
        entry.add_alt(url.sub(/\{id\}/, self.identifier.to_s), type='text/html', title="Link to native interface")
      end
      entry.to_hash 
    end    
    
    def atom();end
  end
end
    