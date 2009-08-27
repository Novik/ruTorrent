#!/bin/sh

log="NO"
log_file="/tmp/erasedata.log"

# Don't use $1, because of possible spaces in data dirname.
# I've not found, how to force rTorrent to enclose variable with "",
item="$@"
[ "x$log" = "xYES" ] && echo "item=$item" >> $log_file

# handle directories only
[ -d "$item" ] || return 1

can_delete=1
for fn in "$item"/* ; do

    #echo "fn=$fn"
    [ "x$log" = "xYES" ] && echo "fn=$fn" >> $log_file

    # if $item is empty - it can be deleted now
    [ "x$fn" = "x$item/*" ] && break

    # if $fn is a directory, then
    if [ -d "$fn" ] ; then
        # check it...
        $0 "$fn"
        # if check fail - directory $item can't be deleted
        [ "x$?" != "x0" ] && can_delete=0
    else
        # if any file found in $item - directory $item can't be deleted
        can_delete=0
        continue
    fi

done

[ "x$can_delete" != "x1" ] && return 1

# we can delete directory $item
#echo "$item"
[ "x$log" = "xYES" ] && echo "2del=$item" >> $log_file

rmdir "$item"

return $?

