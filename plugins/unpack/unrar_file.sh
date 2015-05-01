#!/bin/sh
#
# $1 - unrar
# $2 - archive
# $3 - output directory with tail slash
# $5 - delete archive after auto unpack

"$1" x -ai -c- -kb -o+ -p- -y -v -- "$2" "$3"

ret=$?
if [ $ret -le 1 ] && [  "$5" = 'true' ] ; then
	rm "$2"
fi
