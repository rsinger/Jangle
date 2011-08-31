require 'rubygems'
require 'json'
require 'net/http'
require 'test/unit'
require 'uri'
require 'time'
require 'rexml/document'
require 'cgi'

module JangleTest
  class Connector
    @@conn = nil
    attr_accessor :base_uri, :entities, :username, :password
    private_class_method :new
    def self.create
      @@conn = new unless @@conn
      @@conn
    end
    
    def gather_entities
      @entities = {}
      response = JangleTest::HttpClient.get(self.base_uri+"/services/", {"Accept"=>"application/json"})
      json = JSON.parse(response.body)
      json['entities'].each do | key, entity |
        @entities[key] = entity
      end
    end
  end
  class HttpClient
    def self.get(uri_str,header_opts={}, limit=10)
      raise ArgumentError, 'HTTP redirect too deep' if limit == 0
      uri = URI.parse(uri_str)
      puts uri.to_s
      response = Net::HTTP.start(uri.host, uri.port) do | http |
        http.get(uri.request_uri, header_opts)
      end
      case response
      when Net::HTTPSuccess     then response
      when Net::HTTPRedirection then self.get(response['location'], header_opts, limit - 1)
      else
        response.error!
      end
    end
  end  
end

if ARGV[0]
  connector = JangleTest::Connector.create
  connector.base_uri = ARGV[0].sub(/\/$/,'')
  if ARGV[1]
    connector.username = ARGV[1]
  end
  if ARGV[2]
    connector.username = ARGV[2]
  end
end
class ServiceTest < Test::Unit::TestCase
  def setup
    @conn = JangleTest::Connector.create
    assert @conn.base_uri    
    @response = JangleTest::HttpClient.get(@conn.base_uri+"/services/", {"Accept"=>"application/json"})    
  end
  def test_service_response_exists
    # Test to make sure there's a services response somewhere -- can follow redirects
    assert_equal("200", @response.code)
    # Check proper content type
    assert_equal("application/json",@response.content_type)
    # Make sure there's a JSON object returned
    assert_nothing_raised do
      j = JSON.parse(@response.body)
    end
  end
  
  def test_service_response_sanity

    json = JSON.parse(@response.body)
    # Check for the mandatory services elements
    assert json['title'] 
    assert_equal("1.0", json['version'])
    assert_equal("services", json['type'])
    assert json['request']
    assert_nothing_thrown do
      URI.parse(json['request'])
    end
    assert json['entities']
    # The entities key must be a JSON object
    assert_kind_of(Hash, json['entities'])
    # There must be at least one entity present
    assert json['entities'].keys.length > 0
    @conn.entities = {}
    json['entities'].keys.each do | key |
      entity = json['entities'][key]
      # Check that the entities are valid
      assert_match(/^Actor$|^Collection$|^Item$|^Resource$/, key)
      assert entity['path']
      # Is this a legal URI?
      assert_nothing_raised do
        URI.parse(entity['path'])
      end
      assert entity['title']
      assert entity.has_key?('searchable')
      # Is the URI to the explain document legal?
      if entity['searchable']
        assert_nothing_raised do
          URI.parse(entity['searchable'])
        end
      end
      
      if entity['categories']
        assert_kind_of(Array, entity['categories'])
      end
      @conn.entities[key] = entity
    end
    # Check for categories, which are optional
    if json['categories']
      assert_kind_of(Hash, json['categories'])
      json['categories'].each do | category, vals |        
        assert_kind_of(Hash, vals)
      end
    end
  end  
end

class EntityTest < Test::Unit::TestCase
  def setup
    @conn = JangleTest::Connector.create
    assert @conn.base_uri
    if @conn.entities.nil? || @conn.entities.empty?
      @conn.gather_entities
    end
    # Make sure we have something to test
    assert_kind_of(Hash, @conn.entities)
    assert @conn.entities.length > 0    
  end
  
  def relative_to_absolute_uri(location)
    u = URI.parse(@conn.base_uri)
    uri = URI.parse(location)
    if uri.path =~ /^\//
      u.path = uri.path
    else
      unless u.path =~ /\/$/
        u.path << "/"
      end
      u.path << uri.path
    end    
    u.query = uri.query if uri.query
    u.fragment = uri.fragment if uri.fragment
    u.to_s
  end
  def test_entity_base_paths    
    @conn.entities.each do | entity, values |
      puts "Testing entity:  #{entity}"
      response = JangleTest::HttpClient.get(@conn.base_uri+values['path'],{'Accept'=>'application/json'})
      assert_equal("200",response.code)
      assert_equal("application/json",response.content_type)
      json = {}
      assert_nothing_raised do
        json = JSON.parse(response.body)
      end
      assert_equal('feed',json['type'])
      assert json['request']
      assert_nothing_raised do
        URI.parse(json['request'])
      end    
      assert json['totalResults']  
      assert_kind_of(Fixnum, json['totalResults'])
      assert json['offset']
      assert_kind_of(Fixnum, json['offset'])
      assert json['offset'] >= 0
      assert json['time']
      assert_nothing_raised do
        Time.parse(json['time'])
      end
      assert_match(/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}/, json['time'])
      assert json['formats']
      assert_kind_of(Array, json['formats'])
      json['formats'].each do | format |
        assert_nothing_raised do
          URI.parse(format)
        end
      end
      if json['alternate_formats']
        json['alternate_formats'].each do | format, location |
          puts "Retrieving alternate format:  #{format}"
          if URI.parse(location).relative?
            location = relative_to_absolute_uri(location)
          end
          res = JangleTest::HttpClient.get(location, {"Accept"=>"application/json"})
          assert_equal("200",res.code)
          assert_equal("application/json",res.content_type)
          j = {}
          assert_nothing_raised do 
            j = JSON.parse(res.body)
          end
          assert j["formats"].index(format)
        end
      end
      if json['stylesheets']
        json['stylesheets'].each do | stylesheet |
          puts "Verifying stylesheet:  #{format}"
          if URI.parse(stylesheet).relative?
            location = relative_to_absolute_uri(stylesheet)
          end          
          res = JangleTest::HttpClient.get(stylesheet)
          assert_equal("200",res.code)
          assert_nothing_raised do 
            doc = REXML::Document.new(res)
          end
        end
      end
      if json['categories']
        assert_kind_of(Array, json['categories'])
      end
      assert json['data']
      tested_formats = []
      tested_relationships = []
      tested_stylesheets = []
      assert_kind_of(Array, json['data'])
      if json['data'].length > 0
        json['data'].each do | datum |
          assert_kind_of(Hash, datum)
          assert datum['id']
          assert_nothing_raised do
            URI.parse(datum['id'])
          end
          assert datum['title']
          assert !datum['title'].empty?
          assert datum['updated']
          assert_nothing_raised do
            Time.parse(datum['updated'])
          end
          assert_match(/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}/, datum['updated'])
          if datum['created']
            assert_nothing_raised do
              Time.parse(datum['created'])
            end
            assert_match(/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}/, datum['created'])
          end 
          if datum['content']
            assert datum['content_type']
            assert datum['format']
          end
          if datum['content_type']
            assert_match(/^application\/|^audio\/|^example\/|^image\/|^message\/|^model\/|^multipart\/|^text\/|^video/,datum['content_type'])
            assert datum['content']
            assert datum['format']
          end
          if datum['format']
            assert_nothing_raised do
              URI.parse(datum['format'])
            end
            assert datum['content']
            assert datum['content_type']
          end
          if datum['alternate_formats']

            datum['alternate_formats'].each do | format_uri, location |
              assert_nothing_raised do
                URI.parse(format_uri)
                URI.parse(location)
              end
              if URI.parse(location).relative?
                location = relative_to_absolute_uri(location)
              end
              next if tested_formats.index(format_uri)
              res = JangleTest::HttpClient.get(location, {"Accept"=>"application/json"})
              assert_equal("200",res.code)
              assert_equal("application/json",res.content_type)
              j = {}
              assert_nothing_raised do 
                j = JSON.parse(res.body)
              end
              puts format_uri
              assert j["formats"].index(format_uri)  
              tested_formats << format_uri                      
            end
          end
          if datum['relationships']
            valid_relationships = ["http://jangle.org/vocab/Entities#Actor", "http://jangle.org/vocab/Entities#Collection",
              "http://jangle.org/vocab/Entities#Item","http://jangle.org/vocab/Entities#Resource"]

            datum['relationships'].each do | relation_uri, location |
              assert valid_relationships.index(relation_uri)
              assert_nothing_raised do
                URI.parse(location)
              end
              if URI.parse(location).relative?

                location = relative_to_absolute_uri(location)
              end
              next if tested_relationships.index(relation_uri)
              res = JangleTest::HttpClient.get(location, {"Accept"=>"application/json"})
              assert_equal("200",res.code)
              assert_equal("application/json",res.content_type)
              j = {}
              assert_nothing_raised do 
                j = JSON.parse(res.body)
              end
              tested_relationships << relation_uri
            end
          end
          if datum['links']
            assert_kind_of(Hash, datum['links'])
            datum.links.each do | rel, attribs |
              assert_kind_of(Hash, attribs)
              assert attribs['href']
              assert_nothing_raised do
                URI.parse(assert['href'])
              end
            end
          end
          if datum['stylesheet']
            assert_nothing_raised do
              URI.parse(datum['stylesheet'])
            end
            unless tested_stylesheets.index(datum['stylesheet'])
              location = datum['stylesheet']
              if URI.parse(datum['stylesheet']).relative?
                location = relative_to_absolute_uri(datum['stylesheet'])
              end
              res = JangleTest::HttpClient.get(location)
              assert_equal("200",res.code)
              assert_nothing_raised do
                REXML::Document.new(res.body)
              end
            end
          end
          if datum['categories']
            assert_kind_of(Array, datum['categories'])
          end
        end
        
      else
        warn("Warning, empty set of data")
      end
    end
  end
  
end

class SearchTest < Test::Unit::TestCase
  def setup
    @conn = JangleTest::Connector.create
    assert @conn.base_uri
    if @conn.entities.nil? || @conn.entities.empty?
      @conn.gather_entities
    end 
  end  
  
  def relative_to_absolute_uri(location)
    u = URI.parse(@conn.base_uri)
    uri = URI.parse(location)
    if uri.path =~ /^\//
      u.path = uri.path
    else
      unless u.path =~ /\/$/
        u.path << "/"
      end
      u.path << uri.path
    end    
    u.query = uri.query if uri.query
    u.fragment = uri.fragment if uri.fragment
    u.to_s
  end
  
  def test_explain_responses
    @conn.entities.each do | entity, values |
      if values['searchable']
        puts "Testing explain response for #{entity}"
        location = values['searchable']
        if URI.parse(location).relative?
          location = relative_to_absolute_uri(location)
        end
        response = JangleTest::HttpClient.get(location, {"Accept"=>"application/json"})
        assert_equal("200", response.code)
        assert_equal("application/json",response.content_type)
        json = {}
        assert_nothing_raised do
          json = JSON.parse(response.body)
        end
        assert_equal('explain',json['type'])
        assert json['request']
        assert json['shortname']
        assert json['description']
        assert json['description'].length <= 1024
        assert json['template']
        @conn.entities[entity]["search_template"] = json['template']
        assert_nothing_raised do
          assert_match(/[A-z]*=\{searchTerms\}/,json['template'])
        end
        if json['contact']
          assert_match(/@/,json['contact'])
          username,mailhost = json['contact'].split("@")
          assert_match(/\./,mailhost)
        end
        if json['tags']
          assert_kind_of(Array, json['tags'])
        end
        if json['longname']
          assert json['longname'].length <= 48
        end
        if json['image']
          assert_kind_of(Hash, json['image'])
          assert json['image']['location']
          assert_nothing_raised do
            URI.parse(json['image']['location'])
          end
        end
        if json['query']
          if json['query']['example']
            @conn.entities[entity]['example_query'] = json['query']['example']
          else
            warn("No example query string to test against search target.")
          end
          if json['query']['context-sets']
            @conn.entities[entity]['context-sets'] = json['query']['context-sets']
            assert_kind_of(Array,json['query']['context-sets'])
            json['query']['context-sets'].each do | ctx_set |
              assert_kind_of(Hash, ctx_set)
              assert ctx_set['identifier']
              assert_nothing_raised do
                URI.parse(ctx_set['identifier'])
              end
              assert ctx_set['name']
              assert ctx_set['indexes']
              assert_kind_of(Array, ctx_set['indexes'])
              
            end
          else
            warn("No CQL indexes defined.")
          end
        else
          warn("No query element, unable to determine CQL indexes and no 'example' search to try.")
        end
        
        if json['developer']
          assert json['developer'].length <= 64
        end
        
        if json['attribution']
          assert json['attribution'].length <= 256
        end
        
        if json['syndicationright']
          assert ["open","limited","private","closed"].index(json['syndicationright'])
        end
        
        if json['adultcontent']
          assert_kind_of(TrueClass, json['adultcontent'])
        elsif json.has_key?('adultcontent')
          assert_kind_of(FalseClass, json['adultcontent'])
        end
      else
        warn("#{entity} not set to searchable, skipping.")
      end
      
    end
  end
  
  def test_search
    @conn.entities.each do | entity, values |
      if values['searchable']
        puts "Testing search response for #{entity}"
        unless values['example_query']
          warn("No example query, skipping.")
          next
        end
        assert values['search_template']
        query_url = values['search_template']
        #query_url.sub!(/\{searchTerms\}/,CGI.escape(values['example_query']))
        canned_query = {"count"=>1,"startIndex"=>0,"startPage"=>1,"language"=>"en-us","inputEncoding"=>"utf-8","outputEncoding"=>"utf-8","searchTerms"=>values['example_query']}
        query_parts = query_url.split("&")
        test_query_parts = []
        query_parts.each do | part |
          param, value = part.split("=")
          unless value.match(/^\{.*\}$/)
            test_query_parts << part
          end
          if value.match(/\?\}/)
            if param.match(/\?/)
              test_query_parts << param
            end
            next
          end
          key = value.gsub(/[{}]/,'')
          test_query_parts << "#{param}=#{CGI.escape(canned_query[key])}"
        end
        location = test_query_parts.join("&")
        if URI.parse(location).relative?
          location = relative_to_absolute_uri(location)
        end
        response = JangleTest::HttpClient.get(location, {"Accept"=>"application/json"})
        assert_equal("200", response.code)
        assert_equal("application/json",response.content_type)
        json = {}
        assert_nothing_raised do
          json = JSON.parse(response.body)
        end     
        assert_equal("search",json['type'])
        assert json['request']
        assert json['time']
        assert json['offset'] == 0
        assert json['totalResults'] > 0
        assert_kind_of(Array, json['data'])
        assert json['data'].length > 0
      end
    end
  end
end

class CategoryTest < Test::Unit::TestCase
  def setup
    @conn = JangleTest::Connector.create
    assert @conn.base_uri
    if @conn.entities.nil? || @conn.entities.empty?
      @conn.gather_entities
    end 
  end
  
  def test_categories
    @conn.entities.each do | entity, values |
      if values['categories']
        puts "Testing categories for entity:  #{entity}"
        values['categories'].each do | category |
          uri = URI.parse(@conn.base_uri)
          location = "#{uri.scheme}://#{uri.host}"
          if uri.port
            location << ":#{uri.port}"
          end
          if uri.path
            location << uri.path
          end
          location << values['path']

          unless location.match(/\/$/)
            location += "/"
          end
          location += "-/#{category}/"
          puts "Testing category:  #{category}"
          response = JangleTest::HttpClient.get(location,{"Accept"=>"application/json"})
          assert_equal("200", response.code)
          assert_equal("application/json",response.content_type)
          json = {}
          assert_nothing_raised do
            json = JSON.parse(response.body)
          end          
          assert_equal('feed',json['type'])
          if json['categories']
            assert json['categories'].index(category)
          else
            warn("No categories element in the response root.")
          end
          json['data'].each do | entry |
            if entry['categories']
              assert entry['categories'].index(category)
            else
              warn("No categories element for data object on a category feed.")
            end
          end
        end
      end
    end
  end
end


