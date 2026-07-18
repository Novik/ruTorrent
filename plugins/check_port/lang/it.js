/*
 * PLUGIN CHECK_PORT
 *
 * Italian language file.
 *
 * Author: Gianni
 * Author: Marco (marco.romanelli@protonmail.ch)
 */

 theUILang.checkWebsiteNotFound = "Plugin 'Check_port': Il plugin non funziona. Configurazione non valida.";
 theUILang.checkPort		= "Controlla stato porta";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Stato della porta sconosciuto",
 				  "La porta è chiusa",
 				  "La porta è aperta"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Forza una porta specifica...";
 theUILang.forcePortPrompt	= "Imposta la porta di ascolto (1-65535):";
 theUILang.forcePortInvalid	= "Numero di porta non valido.";

thePlugins.get("check_port").langLoaded();
