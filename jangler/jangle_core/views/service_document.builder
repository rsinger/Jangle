xml.instruct! :xml, :version => '1.0'
xml.service :xmlns=>"http://www.w3.org/2007/app", "xmlns:atom"=>"http://www.w3.org/2005/Atom" do | svc |
  @services.each do | service |
    svc.workspace do | ws |
      ws.atom :title, service["title"]
      service["entities"].each do | resource, data |
        ws.collection :href=>"#{CONFIG['server']['base_url']}#{service["title"]}#{data['path']}" do | coll |
          coll.atom :title, data["title"]
          if data["accept"]
            data["accept"].each do | content |
              coll.accept content
            end
          end
          if data["categories"]
            coll.categories do | cats |
              data["categories"].each do | cat |
                next unless service["categories"][cat]
                cats.atom :categories, {:term=>cat, :scheme=>service["categories"][cat]["scheme"]}
              end
            end
          end
        end
      end
    end
  end
end
