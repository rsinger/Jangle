xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'
xml.tag!("OAI-PMH", {"xmlns"=>"http://www.openarchives.org/OAI/2.0/",
         "xmlns:xsi"=>"http://www.w3.org/2001/XMLSchema-instance",
         "xsi:schemaLocation"=>"http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"}) do | oai |
  oai.responseDate Time.now.to_date.xmlschema
  args = {:verb=>@oai_pmh.verb, :metadataPrefix=>@oai_pmh.metadata_prefix}
  [:from, :until, :set, :identifier].each do | attr |
    args[:attr] = @oai_pmh.send(attr) if @oai_pmh.send(attr)
  end
  
  oai.request(args, @oai_pmh.repo_url)         
  oai.tag!(@oai_pmh.verb) do | verb |
    @oai_pmh.metadata.each do | entry |
      verb.header do | header |
        header.identifier entry.id
        header.datestamp entry.updated.to_date.to_s
        entry.links.each do | link |
          if link.rel == "related" && link.href =~ /\/collections\//
            header.setSpec link.href.split("/").last
          end
        end
      end
      unless @oai_pmh.verb == "ListIdentifiers"
        verb.metadata {
          verb << entry.xml_document.elements['entry'].elements['content'].elements[1].to_s
        }
      end
    end
    if @oai_pmh.resumption_token
      verb.resumptionToken @oai_pmh.resumption_token
    end
  end
end
