#!/bin/sh
#
# $1 - taskNo
# $2 - php
# $3 - torrenttools
# $4 - path
# $5 - piecesize
# $6 - user
# $7 - dir
# $8 - hybrid

if [ ${8} ] ; then
	protocol="hybrid"
else
	protocol="v1"
fi

"${3}" create -l ${5} -o "${7}/temp.torrent" --protocol "${protocol}" "${4}"

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
