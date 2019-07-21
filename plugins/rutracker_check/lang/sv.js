/*
 * PLUGIN RUTRACKER_CHECK
 *
 * Swedish language file.
 *
 * Author: Magnus Holm (holmen@brasse.se)
 */

 theUILang.checkTorrent		= "Sök efter uppdateringar";
 theUILang.chkHdr		= "Torrent-uppdateringskontroll";
 theUILang.checkedAt		= "Senaste sökning";
 theUILang.checkedResult	= "Resultat";
 theUILang.chkResults		= [
 				  "Pågår",
 				  "Updaterad",
 				  "Ingen uppdatering behövs",
 				  "Förmodligen borttagen",
 				  "Fel, uppstigning tracker",
 				  "Fel vid interaktivitet med rTorrent",
 				  "Behövs inte"
 				  ];

thePlugins.get("rutracker_check").langLoaded();
