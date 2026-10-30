#!/bin/sh
#
# $1 - unrar
# $2 - input directory with tail slash
# $3 - output directory with tail slash
# $5 - archive files to delete
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh

. "$(dirname "$0")/stage.sh"

ret=0

# $2 - input directory with tail slash
# $3 - its place in the output, empty or with tail slash
process_directory()
{
	# -ol-: never a symbolic link, see unrar_file.sh.
	unpack_rar_links "$1" "$2."
	unpack_extract "$3" "$1" x -ai -c- -kb -o+ -ol- -p- -y -v -- "$2."
	last=$?
	[ $last -ge 1 ] && [ $last -ne 10 ] && ret=$last
	for fn in "$2"* ; do
		if [ -d "${fn}" ] && [ ! -L "${fn}" ] ; then
			name=$(basename "${fn}")
			process_directory "$1" "${fn}/" "$3${name}/"
			last=$?
			[ $last -ge 1 ] && [ $last -ne 10 ] && ret=$last
		fi
	done
	return $ret
}

process_directory "$1" "$2" ""
ret=$?

if [ $ret -eq 0 ] && [ "$5" != '' ] ; then
	OIFS=$IFS
	IFS=';'
	for file in "$5"
	do
		rm $file
	done
	IFS=$OIFS
fi

exit $ret
