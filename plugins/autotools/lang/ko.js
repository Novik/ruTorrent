/*
 * PLUGIN AUTOTOOLS
 *
 * Korean language file.
 *
 * Author: Limerainne (limerainne@gmail.com)
 */

 var s_PluginFail			= "플러그인이 동작하지 않습니다.";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel		= "\"AutoLabel\" 기능 동작, 형식:";
 theUILang.autotoolsPathToFinished	= "다운로드 완료시 사용할 경로";
 theUILang.autotoolsEnableWatch		= "\"AutoWatch\" 기능 동작";
 theUILang.autotoolsPathToWatch		= "감시할 디렉토리 경로";
 theUILang.autotoolsWatchStart		= "다운로드 자동 시작";
 theUILang.autotoolsNoPathToFinished	= "Autotools 플러그인: 다운로드 완료시 사용할 경로가 지정되지 않았습니다. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Autotools 플러그인: 감시할 디렉토리 경로가 지정되지 않았습니다. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "수행할 동작";
 theUILang.autotoolsFileOpMove		= "이동";
 theUILang.autotoolsFileOpHardLink	= "하드 링크";
 theUILang.autotoolsFileOpCopy		= "복사";
 theUILang.autotoolsFileOpSoftLink	= "소프트 링크";
 theUILang.autotoolsAddLabel		= "토렌트 라벨을 경로에 덧붙임";
 theUILang.autotoolsAddName		= "토렌트 이름을 경로에 덧붙임";
 theUILang.autotoolsEnableMove		= "\"AutoMove\" 기능 동작, 만약 토렌트 라벨이 다음 필터와 일치할 경우";
 theUILang.autotoolsSkipMoveForFiles	= "패턴과 일치하는 파일이 포함된 토렌트 건너뛰기";

thePlugins.get("autotools").langLoaded();
