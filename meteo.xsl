<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="html" encoding="UTF-8" indent="yes"/>

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

        <xsl:variable name="n" select="count(//echeance)"/>

        <!-- matin = première échéance -->
        <xsl:call-template name="bloc">
            <xsl:with-param name="titre" select="'Matin'"/>
            <xsl:with-param name="e" select="//echeance[1]"/>
        </xsl:call-template>

        <!-- midi = milieu -->
        <xsl:call-template name="bloc">
            <xsl:with-param name="titre" select="'Midi'"/>
            <xsl:with-param name="e" select="//echeance[round($n div 2)]"/>
        </xsl:call-template>

        <!-- soir = dernière échéance -->
        <xsl:call-template name="bloc">
            <xsl:with-param name="titre" select="'Soir'"/>
            <xsl:with-param name="e" select="//echeance[last()]"/>
        </xsl:call-template>
    </div>
</xsl:template>

<xsl:template name="bloc">
    <xsl:param name="titre"/>
    <xsl:param name="e"/>

    <h3><xsl:value-of select="$titre"/></h3>

    <xsl:variable name="tK" select="number($e/temperature/sol)"/>
    <xsl:variable name="tC" select="round($tK - 273.15)"/>

    <!-- ATTENTION: <10m> commence par un chiffre => XPath invalide si on écrit /10m -->
    <xsl:variable name="windMs" select="number($e/vent_moyen/*[name()='10m'])"/>
    <xsl:variable name="windKmh" select="round($windMs * 3.6)"/>

    <xsl:variable name="pluie" select="number($e/pluie)"/>

    <ul>
        <li>
            🌡️ Température :
            <xsl:value-of select="$tC"/>°C
            <xsl:choose>
                <xsl:when test="$tC &lt;= 0"> 🧊</xsl:when>
                <xsl:when test="$tC &gt;= 25"> 🔥</xsl:when>
            </xsl:choose>
        </li>

        <li>
            💨 Vent :
            <xsl:value-of select="$windKmh"/> km/h
            <xsl:choose>
                <xsl:when test="$windKmh &gt;= 40"> ⚠️</xsl:when>
                <xsl:when test="$windKmh &gt;= 25"> 🌬️</xsl:when>
            </xsl:choose>
        </li>

        <li>
            <xsl:choose>
                <xsl:when test="$pluie &gt; 0">🌧️ Pluie</xsl:when>
                <xsl:otherwise>☀️ Pas de pluie</xsl:otherwise>
            </xsl:choose>
        </li>
    </ul>
</xsl:template>

</xsl:stylesheet>
