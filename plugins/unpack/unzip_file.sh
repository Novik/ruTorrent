#!/bin/sh
#
# $1 - unzip
# $2 - archive
# $3 - output directory with tail slash
# $5 - delete archive after auto unpack

mkdir -p "$3"
"$1" -o "$2" -d "$3"

ret=$?
[ $ret -le 1 ] && echo 'All OK'
if [ $ret -le 1 ] && [ "$5" = 'true' ] ; then
	rm "$2"
fi

exit $ret
