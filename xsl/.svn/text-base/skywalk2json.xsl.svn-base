<xsl:stylesheet 
xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
xmlns:j.0="http://talisbase.talis.com/bibrdf#"
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
xmlns:os="http://a9.com/-/spec/opensearch/1.1/"
xmlns:rss1="http://purl.org/rss/1.0/">
<xsl:output indent="no" omit-xml-declaration="yes" method="text" encoding="UTF-8"/>
<xsl:strip-space elements="*"/>

<xsl:template name="escape-quote">
 <xsl:param name="string"/>
 <xsl:variable name="quot">"</xsl:variable>
<xsl:choose>
 <xsl:when test='contains($string, $quot)'>
  <xsl:value-of select="substring-before($string,$quot)" />
	<xsl:text>\"</xsl:text>
	<xsl:call-template name="escape-quote">
	 <xsl:with-param name="string"
          select="substring-after($string, $quot)" />
	</xsl:call-template>
 </xsl:when>
 <xsl:otherwise>
  <xsl:value-of select="$string" />
 </xsl:otherwise>
</xsl:choose>
</xsl:template>

<xsl:template name="escape-xml">
 <xsl:param name="string"/>
 <xsl:variable name="quot">"</xsl:variable>
 <xsl:variable name="apos">'</xsl:variable>
 <xsl:variable name="amp">&amp;</xsl:variable>
 <xsl:variable name="lt">&lt;</xsl:variable>
 <xsl:variable name="gt">&gt;</xsl:variable>
<xsl:choose>
 <xsl:when test='contains($string, $amp)'>
  <xsl:value-of select="substring-before($string,$amp)" />
	<xsl:text>&amp;amp;</xsl:text>
	<xsl:call-template name="escape-xml">
	 <xsl:with-param name="string"
          select="substring-after($string, $amp)" />
	</xsl:call-template>
 </xsl:when>
 <xsl:when test='contains($string, $quot)'>
  <xsl:value-of select="substring-before($string,$quot)" />
	<xsl:text>&amp;quot;</xsl:text>
	<xsl:call-template name="escape-xml">
	 <xsl:with-param name="string"
          select="substring-after($string, $quot)" />
	</xsl:call-template>
 </xsl:when>
 <xsl:when test='contains($string, $apos)'>
  <xsl:value-of select="substring-before($string,$apos)" />
	<xsl:text>&amp;apos;</xsl:text>
	<xsl:call-template name="escape-xml">
	 <xsl:with-param name="string"
          select="substring-after($string, $apos)" />
	</xsl:call-template>
 </xsl:when>
 <xsl:when test='contains($string, $lt)'>
  <xsl:value-of select="substring-before($string,$lt)" />
	<xsl:text>&amp;lt;</xsl:text>
	<xsl:call-template name="escape-xml">
	 <xsl:with-param name="string"
          select="substring-after($string, $lt)" />
	</xsl:call-template>
	</xsl:when>
	<xsl:when test='contains($string, $gt)'>
   <xsl:value-of select="substring-before($string,$gt)" />
 	<xsl:text>&amp;gt;</xsl:text>
 	<xsl:call-template name="escape-xml">
 	 <xsl:with-param name="string"
           select="substring-after($string, $gt)" />
 	</xsl:call-template>
  
 </xsl:when>
 <xsl:otherwise>
  <xsl:value-of select="$string" />
 </xsl:otherwise>
</xsl:choose>
</xsl:template>

<xsl:template name="bib-rdf">
   <xsl:text>&lt;rdf:RDF xmlns:j.0=&quot;http://talisbase.talis.com/bibrdf#&quot; xmlns:rdf=&quot;http://www.w3.org/1999/02/22-rdf-syntax-ns#&quot;&gt;&lt;rdf:Description about=&quot;</xsl:text><xsl:value-of select="@rdf:about"/><xsl:text>&quot;&gt;</xsl:text>
   <xsl:for-each select="j.0:*">
     <xsl:text>&lt;j.0:</xsl:text><xsl:value-of select="local-name()"/><xsl:text>&gt;</xsl:text>
     <!-- Copy the current node -->
     <xsl:copy>
       <!-- Including any attributes it has and any child nodes -->
       <xsl:call-template name="escape-xml"><xsl:with-param name="string"><xsl:apply-templates select="@*|node()"/></xsl:with-param></xsl:call-template>
     </xsl:copy>
     <xsl:text>&lt;/j.0:</xsl:text><xsl:value-of select="local-name()"/><xsl:text>&gt;</xsl:text>
   </xsl:for-each>
   <xsl:text>&lt;rdf:type rdf:resource=&quot;http://talisbase.talis.com/bibrdf#MarcRecord&quot;/&gt;&lt;/rdf:Description&gt;&lt;/rdf:RDF&gt;</xsl:text>
</xsl:template>

<xsl:template match="/">
  <xsl:text>{</xsl:text>
  <xsl:for-each select="//os:*">&quot;<xsl:value-of select="local-name()"/>&quot;:&quot;<xsl:value-of select="."/>&quot;,</xsl:for-each>
  <xsl:text>"results":[</xsl:text>
  <xsl:for-each select="rdf:RDF/rss1:item">
  <xsl:text>{</xsl:text>
  <xsl:text>&quot;bibrdf&quot;:&quot;</xsl:text>
  <xsl:call-template name="escape-quote"><xsl:with-param name="string"><xsl:call-template name="bib-rdf"/></xsl:with-param></xsl:call-template>
  <xsl:text>&quot;,</xsl:text>
  <xsl:for-each select="j.0:*">      
    <xsl:text>&quot;</xsl:text><xsl:value-of select="local-name()"/><xsl:text>&quot;:&quot;</xsl:text>
    <xsl:call-template name="escape-quote"><xsl:with-param name="string" select="."/></xsl:call-template>
    <xsl:text>&quot;</xsl:text>
  <xsl:choose>
    <xsl:when test="position() = last()"></xsl:when>
    <xsl:otherwise><xsl:text>,</xsl:text></xsl:otherwise>
  </xsl:choose>
  </xsl:for-each><xsl:text>}</xsl:text>
  <xsl:choose>
    <xsl:when test="position() = last()"></xsl:when>
    <xsl:otherwise><xsl:text>,</xsl:text></xsl:otherwise>
  </xsl:choose>
</xsl:for-each>
<xsl:text>]}</xsl:text>
  </xsl:template>
</xsl:stylesheet>