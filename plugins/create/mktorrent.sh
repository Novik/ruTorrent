#!/bin/sh
#
# $1 - taskNo
# $2 - php
# $3 - mktorrent
# $4 - path
# $5 - piecesize
# $6 - user
# $7 - dir

"${3}" -v -l ${5} -a dummy -o "${7}/temp.torrent" "${4}"
last=$? 
chmod a+r "${7}/temp.torrent"
if [ $last -eq 0 ] ; then
	echo 'Try to correct torrent file...'
	cd "$(dirname $0)"
	"${2}" ./correct.php ${1} "${6}"
	last=$?
	if [ $last -eq 0 ] ; then
		echo 'Done.'
	else
		echo 'Error.'
	fi
fi
exit $last
