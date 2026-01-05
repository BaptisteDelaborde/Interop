<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html" encoding="UTF-8"/>

  <xsl:template match="/">
    <h2>Difficultés de circulation</h2>
    <div id="map"></div>

    <ul id="incidents" style="display:none;">
      <xsl:for-each select="traffic/incident">
        <li
                data-lat="{lat}"
                data-lon="{lon}"
                data-type="{type}"
                data-desc="{description}"
                data-debut="{debut}"
                data-fin="{fin}">
        </li>
      </xsl:for-each>
    </ul>
  </xsl:template>
</xsl:stylesheet>