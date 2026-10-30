#!/bin/sh
#
# $1 - unzip
# $2 - input directory with tail slash
# $3 - output directory with tail slash
# $5 - archive files to delete
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh

. "$(dirname "$0")/stage.sh"

ret=0

# unzip -d makes only the last directory of the path it is given, its last
# argument, and a subdirectory here can be nested.
unzip_into()
{
	for unzip_dest; do :; done
	mkdir -p "$unzip_dest" && "$@"
}

# $2 - input directory with tail slash
# $3 - its place in the output, empty or with tail slash
process_directory()
{
        for fn in "$2"*.zip ; do
        	if [ -f "${fn}" ] && [ -r "${fn}" ] ; then
			unpack_extract "$3" unzip_into "$1" -o "${fn}" -d
			last=$?
			[ $last -ge 1 ] && ret=$last
		fi
	done
	for fn in "$2"* ; do
		if [ -d "${fn}" ] && [ ! -L "${fn}" ] ; then
			name=$(basename "${fn}")
			process_directory "$1" "${fn}/" "$3${name}/"
			last=$?
			[ $last -ge 1 ] && ret=$last
		fi
	done
	return $ret
}

process_directory "$1" "$2" ""
ret=$?

[ $ret -eq 0 ] && echo 'All OK'
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
