#!/bin/sh
#
# $1 - unzip
# $2 - input directory with tail slash
# $3 - output directory with tail slash
# $4 - log file name
# $5 - status file name

ret=0

process_directory()
{
        for fn in "$2"*.zip ; do
        	if [ -f "${fn}" ] && [ -r "${fn}" ] ; then
	        	mkdir -p "$3"
			"$1" -o "${fn}" -d "$3" > /dev/null 2>> "$4"
			last=$?
			[ $last -gt 1 ] && ret=$last
		fi
	done
	for fn in "$2"* ; do
		if [ -d "${fn}" ] && [ ! -L "${fn}" ] ; then
			name=$(basename "${fn}")
			process_directory "$1" "${fn}/" "$3${name}/" "$4" 
			last=$?
			[ $last -gt 1 ] && ret=$last
		fi
	done
	return $ret
}

process_directory "$1" "$2" "$3" "$4"
ret=$?

chmod a+r "$4"

if [ "x$5" != "xdummy" ] ; then
	echo $ret > "$5"
	chmod a+r "$5"
	exit 0
else
	exit $ret
fi
