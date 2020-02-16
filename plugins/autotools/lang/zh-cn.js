/*
 * PLUGIN AUTOTOOLS
 *
 * Chinese Simplified language file.
 *
 * Author: 
 */

 var s_PluginFail			= "插件将不会工作.";

 theUILang.autotools			= "自动工具";
 theUILang.autotoolsEnableLabel		= "启用 \"自动标签\" 功能, 模板:";
 theUILang.autotoolsPathToFinished	= "下载完成路径";
 theUILang.autotoolsEnableWatch		= "启用 \"自动监视\" 功能";
 theUILang.autotoolsPathToWatch		= "监视路径";
 theUILang.autotoolsWatchStart		= "自动开始下载";
 theUILang.autotoolsNoPathToFinished	= "Autotools 插件: 到 \"已完成\"文件夹的路径未设置. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Autotools 插件: 到 \"监视\" 文件夹的路径未设置. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "操作类型";
 theUILang.autotoolsFileOpMove		= "移动";
 theUILang.autotoolsFileOpHardLink	= "硬链接";
 theUILang.autotoolsFileOpCopy		= "复制";
 theUILang.autotoolsFileOpSoftLink	= "软链接";
 theUILang.autotoolsAddLabel		= "将 torrent 的标签添加到路径中";
 theUILang.autotoolsAddName		= "将 torrent 的名称添加到路径中";
 theUILang.autotoolsEnableMove		= "启用 \"自动移动\", 如果 torrent 的标签符合过滤器";
 theUILang.autotoolsSkipMoveForFiles	= "跳过 torrents, 如果它包含符合表达式的文件";

thePlugins.get("autotools").langLoaded();
