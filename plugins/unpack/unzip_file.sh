#!/bin/sh
#
# $1 - unzip
# $2 - archive
# $3 - output directory with tail slash
# $5 - archive files to delete
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh

. "$(dirname "$0")/stage.sh"

unpack_extract "" "$1" -o "$2" -d
ret=$?

[ $ret -eq 0 ] && echo 'All OK'
if [ $ret -eq 0 ] && [ "$5" != '' ] ; then
	rm "$5"
fi

exit $ret
