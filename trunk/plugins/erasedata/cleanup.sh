#!/bin/sh

log="NO"
log_file="/tmp/erasedata.log"

# Don't use $1, because of possible spaces in data dirname.
# I've not found, how to force rTorrent to enclose variable with "",
item="$@"
[ "x$log" = "xYES" ] && echo "item=$item" >> $log_file

# !!! bash doesn't allow "return" command from base script,
# so we have to replace them to "exit"

# handle directories only
[ -d "$item" ] || exit 1
# check for "" (?! seems, that on some systems "" is treated as a directory )
[ "x$item" = "x" ] && exit 1
# ok, a condom for a candle :)
[ "x$item" = "x/" ] && exit 1
# ignore symlinks (hope, there wouldn't be any in torrents data)
[ -L "$item" ] && exit 1

can_delete="YES"
# check if $item is not empty (hope, this method works everywhere)
if [ "x$(ls "$item")" != "x" ] ; then
    for fn in "$item"/* ; do
        [ "x$log" = "xYES" ] && echo "fn=$fn" >> $log_file

        # if $item is empty - it can be deleted now (fail on Linux (bash?))
        [ "x$fn" = "x$item/*" ] && break

        # if $fn is a directory and not a symlink, then
        if [ -d "$fn" ] && [ ! -L "$fn" ] ; then
            # check it...
            $0 "$fn"
            # if check fail - directory $item can't be deleted
            [ "x$?" != "x0" ] && can_delete="NO"
        else
            # if a file was found in $item directory - $item can't be deleted
            can_delete="NO"
            continue
        fi
    done
fi

[ "x$can_delete" = "xYES" ] || exit 1

# we can delete directory $item
[ "x$log" = "xYES" ] && echo "2del=$item" >> $log_file

rmdir "$item"

exit $?

