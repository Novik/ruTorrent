/*
 * PLUGIN CHECK_PORT
 *
 * Korean language file.
 *
 * Author: Limerainne (limerainne@gmail.com)
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: Plugin will not work. Invalid configuration";
 theUILang.checkPort		= "포트 상태 검사";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "포트 상태 알 수 없음",
 				  "포트 닫힘",
 				  "포트 열림"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "특정 포트 강제 지정...";
 theUILang.forcePortPrompt	= "수신 포트 설정 (1-65535):";
 theUILang.forcePortInvalid	= "잘못된 포트 번호입니다.";

thePlugins.get("check_port").langLoaded();
