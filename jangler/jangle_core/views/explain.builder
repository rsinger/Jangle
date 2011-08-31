xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'
namespaces = {:xmlns=>"http://a9.com/-/spec/opensearch/1.1/", "xmlns:jangle"=>"http://jangle.org/opensearch/"}
if @connector_response["extensions"]
  @connector_response["extensions"].each do | ext, ext_data |
    namespaces["xmlns:#{ext}"] = ext_data["namespace"]
  end
end
xml.OpenSearchDescription namespaces do | osd |
  ["ShortName", "LongName", "Description", "Tags", "Image", "Developer",
    "Attribution", "SyndicationRight", "AdultContent"].each do | tag |
      next unless @connector_response[tag.downcase]
      osd.tag!(tag.to_s, @connector_response[tag.downcase])
  end 
  if @connector_response["template"]
    osd.Url({:type=>"application/atom+xml", :template=>"#{CONFIG['server']['base_url']}#{@service.title}#{@connector_response["template"]}"})
  end
  ["InputEncoding", "OutputEncoding", "Language"].each do | repeatable_tag |
    next unless @connector_response[repeatable_tag.downcase]
    if @connector_response[repeatable_tag.downcase].is_a?(Array)
      @connector_response[repeatable_tag.downcase].each do | tag |
        osd.tag!(repeatable_tag, tag)
      end
    else
      osd.tag!(repeatable_tag, @connector_response[repeatable_tag])
    end
  end
  
  if @connector_response["query"]
    if @connector_response["query"]["example"]
      attribs = {"role"=>"example", "searchTerms"=>@connector_response["query"]["example"]}
    else
      attribs = {}
    end
    if @connector_response["query"]["context-sets"]
      
      osd.Query(attribs) do | qry |
        qry.zr :explain, {"xmlns:zr"=>"http://explain.z3950.org/dtd/2.1/"} do | explain |
          explain.zr :indexInfo do | info |
            @connector_response["query"]["context-sets"].each do | cs |
              info.zr :set, {:name=>cs["name"], :identifier=>cs["identifier"]}
              cs["indexes"].each do | index |
                info.zr :map do | map |
                  map.zr :name, {:set=>cs["name"]}, index
                end
              end
            end
          end
        end
      end
    elsif @connector_response["query"]["example"]
      osd.Query(attribs)
    end
  end
end
