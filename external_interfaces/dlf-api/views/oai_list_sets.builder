xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'
xml.tag!("OAI-PMH", {"xmlns"=>"http://www.openarchives.org/OAI/2.0/",
         "xmlns:xsi"=>"http://www.w3.org/2001/XMLSchema-instance",
         "xsi:schemaLocation"=>"http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"}) do | oai |
  oai.responseDate Time.now.to_date.xmlschema
  oai.request({:verb=>@oai_pmh.verb}, @oai_pmh.repo_url)         
  oai.ListSets do | verb |
    @oai_pmh.metadata.each do | entry |
      verb.set do | set |
        set.setSpec entry.id.split("/").last
        set.setName entry.title
      end
    end
    if @oai_pmh.resumption_token
      verb.resumptionToken @oai_pmh.resumption_token
    end
  end
end
