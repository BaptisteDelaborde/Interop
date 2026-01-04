<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <!--
    trafic.xsl
    - Produit un FRAGMENT HTML (pas de <html>, pas de <head>)
    - Crée :
      * <div id="map"></div> pour Leaflet
      * <ul id="incidents" style="display:none"> avec des <li data-*>
    - Les markers sont ajoutés en JS côté page (obligatoire pour Leaflet)
  -->

  <xsl:output method="html" encoding="UTF-8"/>

  <xsl:template match="/">
    <section id="trafic">
      <h2>Difficultés de circulation</h2>

      <!-- Conteneur Leaflet -->
      <div id="map"></div>

      <!-- Liste cachée : le JS lira les data-* pour créer les markers -->
      <ul id="incidents" style="display:none;">
        <xsl:for-each select="traffic/incident">
          <li
            data-lat="{lat}"
            data-lon="{lon}"
            data-type="{type}"
            data-debut="{debut}"
            data-fin="{fin}">
          </li>
        </xsl:for-each>
      </ul>
    </section>
  </xsl:template>

</xsl:stylesheet>
