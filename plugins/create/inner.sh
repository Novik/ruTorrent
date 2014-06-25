#!/bin/sh
#
# $1 - taskNo
# $2 - php
# $3 - createtorrent
# $4 - path
# $5 - piecesize
# $6 - user
# $7 - dir

cd "$(dirname $0)"
"${2}" ./createtorrent.php ${1} "${6}"
last=$? 
if [ $last -eq 0 ] ; then
	echo 'Done.'
else
	echo 'Error.'
fi
exit $last
