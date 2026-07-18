/*
 * PLUGIN CHECK_PORT
 *
 * Greek language file.
 *
 * Author: Chris Kanatas (ckanatas@gmail.com)
 */

 theUILang.checkWebsiteNotFound = "Πρόσθετο Check_port: Το πρόσθετο δεν θα λειτουργήσει. Μη έγκυρη παραμετροποίηση";
 theUILang.checkPort		= "Έλεγχος κατάστασης θύρας";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Η κατάσταση της θύρας είναι άγνωστη",
 				  "Η θύρα είναι κλειστή",
 				  "Η θύρα είναι ανοικτή"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Επιβολή συγκεκριμένης θύρας...";
 theUILang.forcePortPrompt	= "Ορισμός θύρας ακρόασης (1-65535):";
 theUILang.forcePortInvalid	= "Μη έγκυρος αριθμός θύρας.";

thePlugins.get("check_port").langLoaded();
