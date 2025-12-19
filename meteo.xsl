<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="html" encoding="UTF-8"/>

<xsl:param name="ville"/>
<xsl:param name="sourceLoc"/>
<xsl:param name="lat"/>
<xsl:param name="lon"/>

<xsl:template match="/">
  <div>
    <h2>Météo du jour</h2>

    <p>
      <strong>Localisation :</strong>
      <xsl:value-of select="$ville"/>
      (<xsl:value-of select="$sourceLoc"/>)
      —
      <xsl:value-of select="$lat"/>, <xsl:value-of select="$lon"/>
    </p>

    <xsl:variable name="e"
      select="//echeance"/>

    <xsl:call-template name="bloc">
      <xsl:with-param name="titre" select="'Matin'"/>
      <xsl:with-param name="e" select="$e[1]"/>
    </xsl:call-template>

    <xsl:call-template name="bloc">
      <xsl:with-param name="titre" select="'Midi'"/>
      <xsl:with-param name="e"
        select="$e[ceiling(count($e) div 2)]"/>
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

  <h3><xsl:value-of select="$titre"/></h3>

  <!-- Température 2m (Kelvin → Celsius) -->
  <xsl:variable name="tK"
    select="number($e/temperature/level[@val='2m'])"/>
  <xsl:variable name="tC"
    select="round($tK - 273.15)"/>

  <!-- Vent moyen à 10m -->
  <xsl:variable name="wind"
    select="round(number($e/vent_moyen/level[@val='10m']) * 3.6)"/>

  <!-- Pluie -->
  <xsl:variable name="pluie"
    select="number($e/pluie)"/>

  <ul>
    <li>🌡️ <xsl:value-of select="$tC"/>°C</li>
    <li>💨 <xsl:value-of select="$wind"/> km/h</li>
    <li>
      <xsl:choose>
        <xsl:when test="$pluie &gt; 0">🌧️ Pluie</xsl:when>
        <xsl:otherwise>☀️ Pas de pluie</xsl:otherwise>
      </xsl:choose>
    </li>
  </ul>

</xsl:template>

</xsl:stylesheet>
