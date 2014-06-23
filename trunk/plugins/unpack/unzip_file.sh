#!/bin/sh
#
# $1 - unzip
# $2 - archive
# $3 - output directory with tail slash

mkdir -p "$3"
"$1" -o "$2" -d "$3"

ret=$?
[ $ret -le 1 ] && echo 'All OK'

exit $ret
