xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'
namespaces = {:xmlns=>"http://a9.com/-/spec/opensearch/1.1/"}
if @connector_response.extensions
  @connector_response.extensions.each do | ext, ext_data |
    namespaces["xmlns:#{ext}"] = ext_data["namespace"]
  end
end
xml.OpenSearchDescription namespaces do | osd |
  ["ShortName", "LongName", "Description", "Tags", "Image", "Developer",
    "Attribution", "SyndicationRight", "AdultContent"].each do | tag |
      next unless @connector_response.send(tag.downcase.to_sym)
      osd.tag!(tag.to_s, @connector_response.send(tag.downcase.to_sym))
  end 
  if @connector_response.url
    @connector_response.url.each do | url |
      osd.Url({:type=>url["type"], :template=>"#{CONFIG['server']['base_url']}/#{@service.title}#{url["template"]}"})
    end
  end
  ["InputEncoding", "OutputEncoding", "Language"].each do | repeatable_tag |
    next unless @connector_response.send(repeatable_tag.downcase.to_sym)
    if @connector_response.send(repeatable_tag.downcase.to_sym).is_a?(Array)
      @connector_response.send(repeatable_tag.downcase.to_sym).each do | tag |
        osd.tag!(repeatable_tag, tag)
      end
    else
      osd.tag!(repeatable_tag, @connector_response.send(repeatable_tag))
    end
  end
  
  if @connector_response.query
    if @connector_response.query.is_a?(Array)
      @connector_response.query.each do | qry |
        osd.Query({"role"=>"example", "searchTerms"=>qry})
      end
    else
      osd.Query({"role"=>"example", "searchTerms"=>@connector_response.query})
    end
  end
end
