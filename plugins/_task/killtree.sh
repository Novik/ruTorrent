#!/bin/sh
#
# $1 - pgrep
# $2 - pid of the task shell
# $3 - the task's start.sh
#
# A task's real work runs below its direct children (the plugin scripts
# start their programs as children of their own), so the whole tree is
# signalled. Each process is stopped before its children are listed: a
# stopped process cannot start another one, so none escapes the list. None
# is killed before the whole tree is stopped, and then each after its
# descendants, the task shell last: should this script die on the way, a
# later kill still finds the task and what is left of it.
#
# When the children of a process cannot be listed (pgrep is missing, say),
# or a process that has not ended cannot be stopped, the walk ends there:
# the processes stopped so far are resumed and the script exits with 1, so
# that kill() keeps the task for a later kill.
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
	if ! kill -STOP "$1" 2>/dev/null ; then
		# No failure if it has ended: it is gone, or a zombie of another
		# user's.
		state=$(cat "/proc/$1/stat" 2>/dev/null) || return 0
		state=${state##*) }
		[ "${state%% *}" = Z ]
		return
	fi
	echo "$1"
	children=$("$pgrep" -P "$1")
	# pgrep fails with 1 when there are no children, above 1 on an error.
	[ $? -le 1 ] || return 1
	for child in $children; do
		stop_tree "$child" || return 1
	done
}

if pids=$(stop_tree "$2") ; then
	# Reversed: each process after its descendants.
	set --
	for pid in $pids; do
		set -- "$pid" "$@"
	done
	kill -9 "$@" 2>/dev/null
	exit 0
fi
kill -CONT $pids 2>/dev/null
exit 1
