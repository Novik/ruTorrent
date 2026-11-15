#!/bin/sh
#
# $1 - unrar
# $2 - intput directory with tail slash
# $3 - output directory with tail slash
# $4 - unzip
# $5 - archive files to delete
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh

. "$(dirname "$0")/stage.sh"

ret=0
"$(dirname $0)"/unrar_dir.sh "$1" "$2" "$3" "$4" "" "$6" "$7" "$8" "$9" "${10}"
last=$?
[ $last -ge 1 ] && ret=$last
"$(dirname $0)"/unzip_dir.sh "$4" "$2" "$3" "$1" "" "$6" "$7" "$8" "$9" "${10}"
last=$?
[ $last -ge 1 ] && ret=$last
if [ $ret -eq 0 ] && [ "$5" != '' ] ; then
	unpack_delete "$5"
fi
exit $ret
