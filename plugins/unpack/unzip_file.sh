#!/bin/sh
#
# $1 - unzip
# $2 - archive
# $3 - output directory with tail slash
# $5 - unused
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh
# $11... - archives to delete after a successful unpack

. "$(dirname "$0")/stage.sh"

if unpack_zip_pattern "$2" ; then
	unpack_extract "" "$1" -o "$unpack_archive_pattern" -d
	ret=$?
else
	ret=1
fi

[ $ret -eq 0 ] && echo 'All OK'
[ $ret -eq 0 ] && unpack_delete "$@"

exit $ret
