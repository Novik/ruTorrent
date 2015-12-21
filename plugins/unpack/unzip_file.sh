#!/bin/sh
#
# $1 - unzip
# $2 - archive
# $3 - output directory with tail slash
# $5 - archive files to delete
# $6 - unpack temp dir

mkdir -p "$3"

if [ "$6" != '' ] ; then
	mkdir -p "$6"
	"$1" -o "$2" -d "$6"
	ret=$?
else
	"$1" -o "$2" -d "$3"
	ret=$?
fi

[ $ret -eq 0 ] && echo 'All OK'
if [ $ret -eq 0 ] && [ "$5" != '' ] ; then
	rm "$5"
fi

if [ "$6" != '' ] ; then
	cd "$6"
	find . -type d -exec mkdir -p "${3}"/\{} \;
	find . -type f -exec mv -f \{} "${3}"/\{} \;
	[ $? -eq 0 ] && rm -r "$6"
fi

exit $ret
