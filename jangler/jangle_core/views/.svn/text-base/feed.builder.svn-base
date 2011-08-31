xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'
xml.instruct! :"xml-stylesheet", :type=>'text/xsl',:href=>'http://localhost/jangle2xhtml.xsl'
namespaces = {:xmlns=>"http://www.w3.org/2005/Atom", "xmlns:jangle"=>"http://jangle.org/vocab/"}
if @connector_response["type"] == "search"
  namespaces["xmlns:os"]="http://a9.com/-/spec/opensearch/1.1/"
end
if @connector_response["extensions"]
  @connector_response["extensions"].each do | ext, ext_data |
    namespaces["xmlns:#{ext}"] = ext_data["namespace"]
  end
end
xml.feed namespaces do | feed |
  feed.title @service.title
  self_attrs = {:href=>uri_from_path(request.env["REQUEST_URI"]),:rel=>"self"}
  self_attrs["jangle:format"] = @connector_response["formats"][0] if @connector_response["formats"].length == 1
  feed.link self_attrs
  feed.updated(@timestamp||Time.now.strftime("%Y-%m-%dT%H:%M:%SZ"))
  if @connector_response["links"]
    @connector_response["links"].each do | rel, attrs |
      feed.link(:rel=>rel, :href=>"#{CONFIG['server']['base_url']}#{@service.title}#{attrs["href"]}", :type=>attrs["type"])
    end
  end
  
  pager(@connector_response).each do | rel, href |
    feed.link(:rel=>rel, :href=>href, :type=>"application/atom+xml")
  end
  feed.id uri_from_path(request.env["REQUEST_URI"])
  if @connector_response["extensions"]
    @connector_response["extensions"].each do | ext, ext_data |
      ext_data["data"].each do | ext_hash |
        ext_hash.each do | ext_tag, ext_tag_data |
          feed.tag!("#{ext}:#{ext_tag}", ext_tag_data["value"])
        end
      end
    end    
  end
  if @connector_response["alternate_formats"]
    @connector_response["alternate_formats"].each do | format, uri |
      feed.link({:rel=>format, :type=>"application/atom+xml",:href=>uri})
    end
  end
  if @connector_response["type"] == "search"
    feed.os :totalResults, @connector_response["totalResults"]
    feed.os :startIndex, @connector_response["offset"]
    feed.os :itemsPerPage, @connector_response["data"].length
    q = CGI.parse(URI.parse(@connector_response["request"]).query)["query"]
    feed.os :Query, {:role=>"request",:query=>q}
  end
  
  if @connector_response["categories"]
    @connector_response["categories"].each do | cat |
      feed.category :term=>cat
    end
  end    
  @connector_response["data"].each do | entry |
    feed.entry do | ent |
      ent.title entry["title"]
      ent.link :href=>entry["id"], "jangle:format"=>entry["format"]
      if entry["links"]
        entry["links"].keys.each do | link |
          entry["links"][link].each do | attributes |
            u = URI.parse(attributes["href"])
            attributes["href"] = "#{CONFIG["server"]["base_url"]}#{@service.title}#{attributes["href"]}" unless u.absolute?
            ent.link :rel=>link, :type=>attributes["type"], :href=>attributes["href"]
          end
        end
      end
      if entry["relationships"]
        entry["relationships"].each do | rel, href |
          ent.link :rel=>"related", :type=>"application/atom+xml", :href=>href, "jangle:relationship"=>rel
        end
      end
      if entry["alternate_formats"]
        entry["alternate_formats"].each do | rel, arg |
          ent.link :rel=>rel, :type=>"application/atom+xml", :href=>arg
        end      
      end
      if entry["author"]
        if entry["author"].is_a?(Array)
          entry["author"].each do | author |
            ent.author do | auth |
              if author.is_a?(String)
                auth.name author
                next
              end
              unless author["name"].nil? or author["name"].empty?
                auth.name author["name"]
              else
                auth.name "n/a"
              end
              auth.uri author["uri"] if author["uri"]
              auth.email author["email"] if author["email"]
            end
          end
        else
          ent.author do | auth |
            if entry["author"].is_a?(String)
              auth.name entry["author"]
            else
              unless entry["author"]["name"].empty?
                auth.name entry["author"]["name"]
              else
                auth.name "n/a"
              end
              auth.uri entry["author"]["uri"] if entry["author"]["uri"]
              auth.email entry["author"]["email"] if entry["author"]["email"]
            end
          end
        end
      else            
        ent.author do | auth |
          auth.name "n/a"
        end
      end
      unless URI.parse(entry["id"]).absolute?
        ent.id "#{CONFIG["server"]["base_url"]}#{@service.title}#{entry["id"]}"
      else
        ent.id entry["id"]
      end
      ent.updated entry["updated"]
      ent.published entry["published"] if entry["published"]
      if entry["content"]
        ent.content :type=>(entry["content_type"]||"text/plain") do | content |
          payload = entry["content"]
          payload = transform_document(entry["stylesheet"], entry["content"]) if entry["stylesheet"]    
          # If it's XML send it as a child node to the content tag, otherwise escape it.
          if entry["content_type"].match(/xml$/)
            content << payload
          else
            content.text! payload
          end
        end
      end
      ent.summary entry["summary"] if entry["summary"]
      if entry["categories"]
        entry["categories"].each do | cat |
          ent.category :term=>cat
        end
      end
    end
  end
end
