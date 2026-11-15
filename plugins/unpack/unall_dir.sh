#!/bin/sh
#
# $1 - unrar
# $2 - intput directory with tail slash
# $3 - output directory with tail slash
# $4 - unzip
# $5 - unused
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh
# $11... - archives to delete after a successful unpack

. "$(dirname "$0")/stage.sh"

ret=0
"$(dirname $0)"/unrar_dir.sh "$1" "$2" "$3" "$4" "" "$6" "$7" "$8" "$9" "${10}"
last=$?
[ $last -ge 1 ] && ret=$last
"$(dirname $0)"/unzip_dir.sh "$4" "$2" "$3" "$1" "" "$6" "$7" "$8" "$9" "${10}"
last=$?
[ $last -ge 1 ] && ret=$last
[ $ret -eq 0 ] && unpack_delete "$@"
exit $ret
