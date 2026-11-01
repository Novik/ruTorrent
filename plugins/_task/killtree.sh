#!/bin/sh
#
# $1 - pgrep
# $2 - pid of the task shell
#
# A task's real work runs below its direct children (the plugin scripts
# start their programs as children of their own), so the whole tree is
# signalled. Each process is stopped before its children are listed: a
# stopped process cannot start another one, so none escapes the list.

pgrep="$1"

stop_tree()
{
	kill -STOP "$1" 2>/dev/null
	echo "$1"
	for child in $("$pgrep" -P "$1"); do
		stop_tree "$child"
	done
}

kill -9 $(stop_tree "$2") 2>/dev/null
exit 0
