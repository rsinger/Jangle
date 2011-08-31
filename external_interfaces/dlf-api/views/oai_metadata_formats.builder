xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'
xml.tag!("OAI-PMH", {"xmlns"=>"http://www.openarchives.org/OAI/2.0/",
         "xmlns:xsi"=>"http://www.w3.org/2001/XMLSchema-instance",
         "xsi:schemaLocation"=>"http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"}) do | oai |
  oai.responseDate Time.now.to_date.xmlschema
  attributes = {:verb=>@oai_pmh.verb}
  attributes[:identifier] = @oai_pmh.identifier if @oai_pmh.identifier
  oai.request(attributes, @oai_pmh.repo_url)         
  oai.ListMetadataFormats do | verb |
    verb.metadataFormat do | md_fmt |
      md_fmt.metadataPrefix "oai_dc"
      md_fmt.schema "http://www.openarchives.org/OAI/2.0/oai_dc.xsd"
      md_fmt.metadataNamespace "http://www.openarchives.org/OAI/2.0/oai_dc/"
    end
    verb.metadataFormat do | md_fmt |
      md_fmt.metadataPrefix "dc"
      md_fmt.schema "http://dublincore.org/schemas/xmls/qdc/2008/02/11/dc.xsd"
      md_fmt.metadataNamespace "http://purl.org/dc/elements/1.1/"
    end   
    verb.metadataFormat do | md_fmt |
      md_fmt.metadataPrefix "marcxml"
      md_fmt.schema "http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd"
      md_fmt.metadataNamespace "http://www.loc.gov/MARC21/slim"
    end     
  end
end
