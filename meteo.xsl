<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" encoding="UTF-8" indent="yes"/>
    <xsl:strip-space elements="*"/>

    <!-- template principal -->
    <xsl:template match="/">
        <div class="meteo">

            <h3>Météo du jour</h3>

            <!-- matin -->
            <xsl:call-template name="periode">
                <xsl:with-param name="heure">08</xsl:with-param>
                <xsl:with-param name="label">Matin</xsl:with-param>
            </xsl:call-template>

            <!-- midi -->
            <xsl:call-template name="periode">
                <xsl:with-param name="heure">14</xsl:with-param>
                <xsl:with-param name="label">Midi</xsl:with-param>
            </xsl:call-template>

            <!-- soir -->
            <xsl:call-template name="periode">
                <xsl:with-param name="heure">20</xsl:with-param>
                <xsl:with-param name="label">Soir</xsl:with-param>
            </xsl:call-template>

        </div>
    </xsl:template>

    <!-- template pour une période -->
    <xsl:template name="periode">
        <xsl:param name="heure"/>
        <xsl:param name="label"/>

        <!-- on prend la première prévision correspondant à l'heure -->
        <xsl:for-each select="//echeance[substring(@hour,1,2)=$heure][1]">

            <div class="periode">
                <h4><xsl:value-of select="$label"/></h4>

                <ul>
                    <li>
                        🌡️ Température :
                        <xsl:value-of select="temperature/@value"/> °C
                    </li>

                    <li>
                        💨 Vent :
                        <xsl:value-of select="vent_moyen/@value"/> km/h
                    </li>

                    <li>
                        <xsl:choose>
                            <xsl:when test="pluie/@value &gt; 0">
                                🌧️ Pluie : <xsl:value-of select="pluie/@value"/> mm
                            </xsl:when>
                            <xsl:otherwise>
                                ☀️ Pas de pluie
                            </xsl:otherwise>
                        </xsl:choose>
                    </li>
                </ul>
            </div>

        </xsl:for-each>
    </xsl:template>

</xsl:stylesheet>
