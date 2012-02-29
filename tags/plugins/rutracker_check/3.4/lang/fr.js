/*
 * PLUGIN RUTRACKER_CHECK
 *
 * File Name: fr.js
 *      French language file.
 *
 * File Author:
 *    Nicobubulle (nicobubulle@gmail.com)
 */

 theUILang.checkTorrent 	= "Vérifier les MAJ";
 theUILang.chkHdr		= "Vérification de la MAJ du torrent";
 theUILang.checkedAt		= "Dernière vérification";
 theUILang.checkedResult	= "Resultat";
 theUILang.chkResults		= [
				  "En cours",
				  "Mis à jour",
				  "A jour",
				  "Certainement supprimé",
				  "Erreur d'accès au tracker",
				  "Problème d'accès à rTorrent",
				  "Pas besoin"
				  ];

thePlugins.get("rutracker_check").langLoaded();