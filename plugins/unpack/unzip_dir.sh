#!/bin/sh
#
# $1 - unzip
# $2 - input directory with tail slash
# $3 - output directory with tail slash
# $5 - unused
# $6 - unpack temp dir
# $7-$10 - for place.php, see stage.sh
# $11... - archives to delete after a successful unpack

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
			if unpack_zip_pattern "${fn}" ; then
				unpack_extract "$3" unzip_into "$1" -o "$unpack_archive_pattern" -d
				last=$?
			else
				last=1
			fi
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
[ $ret -eq 0 ] && unpack_delete "$@"

exit $ret
