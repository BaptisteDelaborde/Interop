<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <!--
    meteo.xsl
    - Produit un FRAGMENT HTML (pas de <html>, pas de <head>)
    - Résume la journée : Matin / Midi / Soir
    - Affiche des symboles + infos utiles : température, vent, pluie
  -->

  <xsl:output method="html" encoding="UTF-8"/>

  <!-- Paramètres transmis par PHP -->
  <xsl:param name="ville"/>
  <xsl:param name="sourceLoc"/>
  <xsl:param name="lat"/>
  <xsl:param name="lon"/>

  <xsl:template match="/">
    <section id="meteo">
      <h2>Météo du jour</h2>

      <p class="loc">
        <strong>Localisation :</strong>
        <xsl:value-of select="$ville"/>
        (<xsl:value-of select="$sourceLoc"/>)
        — <xsl:value-of select="$lat"/>, <xsl:value-of select="$lon"/>
      </p>

      <!-- On récupère toutes les échéances -->
      <xsl:variable name="e" select="//echeance"/>

      <!-- Matin = première échéance -->
      <xsl:call-template name="bloc">
        <xsl:with-param name="titre" select="'Matin'"/>
        <xsl:with-param name="e" select="$e[1]"/>
      </xsl:call-template>

      <!-- Midi = au milieu -->
      <xsl:call-template name="bloc">
        <xsl:with-param name="titre" select="'Midi'"/>
        <xsl:with-param name="e" select="$e[ceiling(count($e) div 2)]"/>
      </xsl:call-template>

      <!-- Soir = dernière échéance -->
      <xsl:call-template name="bloc">
        <xsl:with-param name="titre" select="'Soir'"/>
        <xsl:with-param name="e" select="$e[last()]"/>
      </xsl:call-template>

    </section>
  </xsl:template>

  <!-- Bloc Matin / Midi / Soir -->
  <xsl:template name="bloc">
    <xsl:param name="titre"/>
    <xsl:param name="e"/>

    <h3><xsl:value-of select="$titre"/></h3>

    <!-- Température 2m (Kelvin -> Celsius) -->
    <xsl:variable name="tK" select="number($e/temperature/level[@val='2m'])"/>
    <xsl:variable name="tC" select="round($tK - 273.15)"/>

    <!-- Vent moyen à 10m (m/s -> km/h) -->
    <xsl:variable name="wind" select="round(number($e/vent_moyen/level[@val='10m']) * 3.6)"/>

    <!-- Pluie -->
    <xsl:variable name="pluie" select="number($e/pluie)"/>

    <ul class="meteo-list">
      <li><xsl:value-of select="$tC"/>°C</li>
      <li><xsl:value-of select="$wind"/> km/h</li>
      <li>
        <xsl:choose>
          <xsl:when test="$pluie &gt; 0">Pluie</xsl:when>
          <xsl:otherwise>Pas de pluie</xsl:otherwise>
        </xsl:choose>
      </li>
    </ul>
  </xsl:template>

</xsl:stylesheet>
