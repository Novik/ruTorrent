 /*
 * PLUGIN DUMP
 *
 * Slovak language file.
 *
 * Author:
 */

theUILang.dumpTorrent		= "Dump .torrent...";
theUILang.dumpErrorNoHash			= "Dump plugin: The request carried no torrent hash.";
theUILang.dumpErrorNoCommand			= "Dump plugin: The request carried no dump command.";
theUILang.dumpErrorNoAnswer			= "Dump plugin: rTorrent did not answer, so no torrent file could be located.";
theUILang.dumpErrorNoSessionDirectory		= "Dump plugin: rTorrent reports no session directory, and this torrent has no tied file.";
theUILang.dumpErrorNoFile			= "Dump plugin: No torrent file at {file}.";
theUILang.dumpErrorUnreadable			= "Dump plugin: Couldn't read file {file} as user {user}, check file permissions.";
theUILang.dumpErrorUnreadableUnknownUser	= "Dump plugin: Couldn't read file {file}, check file permissions.";

thePlugins.get("dump").langLoaded();
