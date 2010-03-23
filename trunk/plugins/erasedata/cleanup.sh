#!/bin/sh

log="NO"
log_file="/tmp/erasedata.log"
item="${@}"

[ "x${log}" = "xYES" ] && echo "item=\"${item}\"" >> "${log_file}"

# !!! bash doesn't allow "return" command from base script,
# so we have to replace them to "exit"

# handle directories only
[ -d "${item}" ] || exit 1
# check for "" (?! seems, that on some systems "" is treated as a directory )
[ "x${item}" = "x" ] && exit 1
# ok, a condom for a candle :)
[ "x${item}" = "x/" ] && exit 1
# ignore symlinks (hope, there wouldn't be any in torrents data)
[ -L "${item}" ] && exit 1

# check if $item is not empty (hope, this method works everywhere)
flist="$(find "${item}" ! -type d)"
if [ "x${flist}" != "x" ] ; then
	[ "x${log}" = "xYES" ] && echo "fn=\"${flist}\"" >> "${log_file}"
	exit 1
fi

# we can delete directory $item
[ "x${log}" = "xYES" ] && echo "2del=\"${item}\"" >> "${log_file}"

# remove all empty dirs
[ "x$(ls "${item}")" != "x" ] && cd "${item}" && find -type d -exec rmdir -p '{}' \;
rmdir "${item}"

exit $?
