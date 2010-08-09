/*
 * PLUGIN RSS
 * 
 * File Name: fr.js
 *      French language file.
 * 
 * File Author:
 *    Nicobubulle (nicobubulle@gmail.com)
 */

 
 theUILang.addRSS               = "Ajouter un flux RSS";
 theUILang.feedURL      = "URL du flux";
 theUILang.alias                = "Alias personnalis\u00e9";
 theUILang.rssAuto      = "T\u00e9l\u00e9charger automatiquement les \u00e9l\u00e9ments publi\u00e9 dans ce flux";
 theUILang.allFeeds     = "Tous les flux";
 theUILang.incorrectURL = "Svp, sp\u00e9cifiez une URL correcte."
 theUILang.cantFetchRSS = "Erreur lors du chargement du flux.";
 theUILang.rssAlreadyExist      = "Ce flux existe d\u00e9j\u00e0.";
 theUILang.rssDontExist         = "Ce flux n'existe pas.";
 theUILang.rssCantLoadTorrent   = "Erreur lors du chargement du .torrent.";
 theUILang.rssStatus    = "RSS";
 theUILang.rssStatusLoaded= "D\u00e9j\u00e0 charg\u00e9";
 theUILang.rssMenuLoad  = "Charger";
 theUILang.rssMenuOpen  = "Ouvrir dans le navigateur";
 theUILang.rssMenuClearHistory  = "Vider l'historique";
 theUILang.rssMenuAddToFilter   = "Ajouter aux favoris";
 theUILang.rssMenuManager       = "Gestionnaire RSS";
 theUILang.rssMenuRefresh       = "Mettre \u00e0 jour le flux";
 theUILang.rssMenuDisable       = "D\u00e9sactiver le flux";
 theUILang.rssMenuEnable        = "Activer le flux";
 theUILang.rssMenuEdit  = "Editer le flux";
 theUILang.rssMenuDelete        = "Supprimer le flux";
 theUILang.rssDeletePrompt      = "Voulez-vous r\u00e9ellement supprimer le flux s\u00e9lectionn\u00e9?";
 theUILang.rssNewFilter = "Nouveau filtre";
 theUILang.rssFilter    = "Filtre";
 theUILang.rssAddFilter = "Ajouter";
 theUILang.rssDelFilter = "Supprimer";
 theUILang.rssCheckFilter       = "?";
 theUILang.rssFiltersLegend     = "Configuration du filtre";
 theUILang.rssIncorrectFilter   = "Filtre erron\u00e9.";
 theUILang.foundedByFilter      = "Correspondances";
 theUILang.rssExclude   = "Exclure";
 theUILang.rssStatusError       = "Erreur lors du chargement";
 theUILang.rssPHPNotFound       = "Plugin 'RSS': rTorrent ne peut pas acc\u00e9der \u00e0 l'interpr\u00e9teur php. Le plugin ne fonctionnera pas.";
 theUILang.rssCurlNotFound= "Plugin 'RSS': rTorrent ne peut pas acc\u00e9der au programme 'curl'. Vous ne pourrez pas utiliser des flux en HTTPS.";
 theUILang.rssCurlNotFound1     = "Plugin 'RSS': Le serveur web ne peut pas acc\u00e9der au programme 'curl'. Vous ne pourrez pas utiliser des flux en HTTPS.";
 theUILang.rssCacheNotAvailable         = "Plugin 'RSS': rTorrent ne peut pas acc\u00e9der au r\u00e9pertoire plugins/rss/cache en Lecture/Ecriture/Ex\u00e9cution. Le plugin ne fonctionnera pas.";
 theUILang.rssUpdaterNotAvailable = "Plugin 'RSS': rTorrent ne peut pas acc\u00e9der au fichier plugins/rss/update.php en Lecture. Le plugin ne fonctionnera pas.";
 theUILang.rssFeeds = "Flux";
 theUILang.rssCheckTitle = "Regarder dans le titre";
 theUILang.rssCheckDescription = "Regarder dans la description";
 theUILang.rssCheckLink = "Regarder dans le lien";
 theUILang.rssMinInterval = "Intervalle minimum";
 theUILang.rssIntervalAlways = "(Correspond toujours)";
 theUILang.rssIntervalOnce = "(Correspond une seule fois)";
 theUILang.rssInterval12h = "12 heures";
 theUILang.rssInterval1d = "1 jour";
 theUILang.rssInterval2d = "2 jours";
 theUILang.rssInterval3d = "3 jours";
 theUILang.rssInterval4d = "4 jours";
 theUILang.rssInterval1w = "1 semaine";
 theUILang.rssInterval2w = "2 semaine";
 theUILang.rssInterval3w = "3 semaine";
 theUILang.rssInterval1m = "1 mois";
 theUILang.rssClearFilter = "Remise \u00e0 z\u00e9ro";
 theUILang.rssMarkAs = "Marquer comme";
 theUILang.rssAsLoaded = "charg\u00e9";
 theUILang.rssAsUnloaded = "d\u00e9charg\u00e9";
 theUILang.addRSSGroup          = "Ajouter un groupe";
 theUILang.editRSSGroup         = "Modifier un groupe";
 theUILang.addRSSGroupContent   = "Contenu";
 theUILang.rssMenuGroupDisable  = "D\u00e9sactiver le groupe";
 theUILang.rssMenuGroupRefresh  = "Mettre à jour le groupe";
 theUILang.rssMenuGroupEnable   = "Activer le groupe";
 theUILang.rssMenuGroupEdit     = "Modifier le groupe";
 theUILang.rssMenuGroupDelete   = "Supprimer le groupe";
 theUILang.rssMenuGroupContentsDelete = "Supprimer le contenu";
 theUILang.rssDeleteGroupPrompt = "\u00cates-vous s\u00fbr de vouloir supprimer ce groupe?";
 theUILang.rssDeleteGroupContentsPrompt = "\u00cates-vous s\u00fbr de vouloir supprimer ce groupe ainsi que tout son contenu?";

thePlugins.get("rss").langLoaded();
