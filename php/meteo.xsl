<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:output method="html" encoding="UTF-8"/>

  <xsl:param name="ville"/>
  <xsl:param name="sourceLoc"/>
  <xsl:param name="lat"/>
  <xsl:param name="lon"/>

  <xsl:template match="/">
    <h2>Météo du jour</h2>

    <p class="loc-info">
      <strong>Localisation : </strong>
      <xsl:value-of select="$ville"/>
      <span style="font-size:0.8em; color:#666;"> (<xsl:value-of select="$sourceLoc"/>)</span>
    </p>

    <div class="meteo-colonnes">

      <xsl:variable name="e" select="//echeance"/>

      <xsl:call-template name="bloc">
        <xsl:with-param name="titre" select="'Matin'"/>
        <xsl:with-param name="e" select="$e[1]"/>
      </xsl:call-template>

      <xsl:call-template name="bloc">
        <xsl:with-param name="titre" select="'Midi'"/>
        <xsl:with-param name="e" select="$e[ceiling(count($e) div 2)]"/>
      </xsl:call-template>

      <xsl:call-template name="bloc">
        <xsl:with-param name="titre" select="'Soir'"/>
        <xsl:with-param name="e" select="$e[last()]"/>
      </xsl:call-template>

    </div>
  </xsl:template>

  <xsl:template name="bloc">
    <xsl:param name="titre"/>
    <xsl:param name="e"/>

    <div class="meteo-col">
      <h3><xsl:value-of select="$titre"/></h3>

      <xsl:variable name="tK" select="number($e/temperature/level[@val='2m'])"/>
      <xsl:variable name="tC" select="round($tK - 273.15)"/>
      <xsl:variable name="wind" select="round(number($e/vent_moyen/level[@val='10m']) * 3.6)"/>
      <xsl:variable name="pluie" select="number($e/pluie)"/>

      <ul class="meteo-details">
        <li>🌡️ <xsl:value-of select="$tC"/>°C</li>
        <li>💨 <xsl:value-of select="$wind"/> km/h</li>
        <li>
          <xsl:choose>
            <xsl:when test="$pluie &gt; 0">🌧️ Pluie</xsl:when>
            <xsl:otherwise>☀️ Sec</xsl:otherwise>
          </xsl:choose>
        </li>
      </ul>
    </div>
  </xsl:template>

</xsl:stylesheet>