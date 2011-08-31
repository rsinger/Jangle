# Here we're extending Vcard to allow for User ids (such as barcodes).
# This is legal within Vcard, just not available in the Ruby library.
require 'vpim/vcard'
module Vpim
  class  Vcard
    class Maker
      def add_uid(value, type=nil)
        uid = Vpim::DirectoryInfo::Field.create( 'UID', value.to_str )
        uid["TYPE"] = type if type
        @card << uid        
      end
    end
  end
end
