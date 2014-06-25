#!/bin/sh
#
# $1 - taskNo
# $2 - php
# $3 - createtorrent
# $4 - path
# $5 - piecesize
# $6 - user
# $7 - tmp

"${3}" -n "${4}" "${7}/temp.torrent"
last=$? 
chmod a+r "${7}/temp.torrent"
if [ $last -le 1 ] ; then
	echo ''
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
