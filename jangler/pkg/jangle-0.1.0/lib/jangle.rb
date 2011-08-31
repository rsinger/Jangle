require 'rubygems'
require 'date'

require 'jangle/responses'
require 'jangle/models'

module Jangle
  class Connector
    def self.init
      load("#{File.dirname(__FILE__)}/jangle/connector.rb")
    end
  end
end
