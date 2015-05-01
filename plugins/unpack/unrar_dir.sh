#!/bin/sh
#
# $1 - unrar
# $2 - input directory with tail slash
# $3 - output directory with tail slash
# $5 - delete archives after auto unpack

ret=0

process_directory()
{
	"$1" x -ai -c- -kb -o+ -p- -y -v -- "$2." "$3"
	last=$?
	[ $last -le 1 ] && ret=$last
	for fn in "$2"* ; do
		if [ -d "${fn}" ] && [ ! -L "${fn}" ] ; then
			name=$(basename "${fn}")
			process_directory "$1" "${fn}/" "$3${name}/"
			last=$?
			[ $last -gt 1 ] && ret=$last
		fi
	done
	return $ret
}

process_directory "$1" "$2" "$3"

ret=$?
if [ $ret -le 1 ] && [ "$5" = 'true' ] ; then
	find "$2" \( -name '*.rar' -o -name '*.raR' -o -name '*.rAr' -o -name '*.rAR' -o -name '*.Rar' -o -name '*.RaR' -o -name '*.RAr' -o -name '*.RAR' -o -name '*.r[0-9][0-9]' -o -name '*.R[0-9][0-9]' -o -name '*.[0-9][0-9][0-9]' \) -exec rm {} +
fi
