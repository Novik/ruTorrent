#!/bin/sh
#
# $1 - pgrep
# $2 - pid of the task shell
# $3 - the task's start.sh
#
# A task's real work runs below its direct children (the plugin scripts
# start their programs as children of their own), so the whole tree is
# signalled. Each process is stopped before its children are listed: a
# stopped process cannot start another one, so none escapes the list.
#
# The pid file outlives the task shell: a task that ended without writing
# its status (a restart, the OOM killer) leaves it behind, and the number
# may since have gone to an unrelated process. So where /proc shows
# command lines, the tree is signalled only while that pid still runs this
# task's start.sh, which every way rTask::run() starts a task passes to the
# shell as its first argument. The two are compared as files: start() and
# kill() may reach the task's directory by different paths, one of them
# through a symbolic link, say.

pgrep="$1"

if [ -r /proc/$$/cmdline ] &&
	! [ "$(tr '\000' '\n' < "/proc/$2/cmdline" | sed -n 2p)" -ef "$3" ] ; then
	exit 0
fi

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
