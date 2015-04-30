#!/bin/sh
#
# $1 - unrar
# $2 - archive
# $3 - output directory with tail slash
# $5 - archive files to delete

"$1" x -ai -c- -kb -o+ -p- -y -v -- "$2" "$3"

ret=$?
if [ $ret -le 1 ] && [ "$5" != '' ] ; then
	rm "$5"
fi
