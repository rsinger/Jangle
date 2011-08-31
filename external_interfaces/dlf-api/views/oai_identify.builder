xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'
xml.tag!("OAI-PMH", {"xmlns"=>"http://www.openarchives.org/OAI/2.0/",
         "xmlns:xsi"=>"http://www.w3.org/2001/XMLSchema-instance",
         "xsi:schemaLocation"=>"http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"}) do | oai |
  oai.responseDate Time.now.to_date.xmlschema
  oai.request({:verb=>"Identify"}, @oai_pmh.repo_url)
  oai.Identify do | identify |
    identify.repositoryName @oai_pmh.repo_name
    identify.baseURL @oai_pmh.repo_url
    identify.protocolVersion "2.0"
    identify.adminEmail @oai_pmh.repo_admin
    identify.granularity "YYYY-MM-DDThh:mm:ssZ"
    identify.deletedRecord "no"
    identify.earliestDatestamp @oai_pmh.metadata.last.updated.xmlschema
  end
end