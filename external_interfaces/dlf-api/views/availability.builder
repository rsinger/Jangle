xml.instruct! :xml, :version=>'1.0', :encoding=>'utf-8'

xml.dlf :collection, ({"xmlns:dlf"=>"http://onlinebooks.library.upenn.edu/schemas/dlf/1.0/",
        "xmlns:xsi"=>"http://www.w3.org/2001/XMLSchema-instance",
        "xsi:schemaLocation"=>"http://onlinebooks.library.upenn.edu/schemas/dlf/1.0/
               http://onlinebooks.library.upenn.edu/schemas/dlfexpanded.xsd"}) do | collection | 
  @records.each do | bib_id, items |
    collection.dlf :record do | record |
      record.dlf :bibliographic, {:id=>bib_id}
      record.dlf :items do | itemset |
        items.each do | item |
          itemset.dlf :item, {:id=>item[:item_id]} do | i |
            i.dlf :simpleavailability do | avail |
              avail.dlf :identifier, (item[:item_id])
              avail.dlf :availabilitystatus, (item[:status])
              avail.dlf :availabilitymsg, (item[:message])
              if item[:date_available]
                avail.dlf :dateavailable, (item[:date_available])
              end              
            end
          end
        end
      end
    end
  end
end
    
