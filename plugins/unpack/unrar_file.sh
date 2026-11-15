#!/bin/sh
#
# $1 - unrar
# $2 - archive
# $3 - output directory with tail slash
# $5 - unused
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh
# $11... - archives to delete after a successful unpack

. "$(dirname "$0")/stage.sh"

unpack_rar_path "$2" || exit 1

# -ol-: unrar never creates a symbolic link. An archive that holds one is
# listed first and refused like a zip's (unpack_rar_links in stage.sh).
unpack_rar_links "$1" "$2"
unpack_extract "" "$1" x -ai -c- -kb -o+ -ol- -p- -y -v -- "$2"
ret=$?

[ $ret -eq 0 ] && unpack_delete "$@"

exit $ret
