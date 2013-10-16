#!/bin/sh
#
# $1 - taskNo
# $2 - php
# $3 - createtorrent
# $4 - path
# $5 - piecesize
# $6 - user
# $7 - tmp

dir="${7}${6}${1}"
mkdir "${dir}"
chmod a+rx "${dir}"
echo $$ > "${dir}/pid"
chmod a+r "${dir}/pid"
"${3}" -l ${5} -a dummy "${4}" "${dir}/temp.torrent" > "${dir}/errors" 2> "${dir}/log"
last=$? 
chmod a+r "${dir}/*"
if [ $last -le 1 ] ; then
	echo 'Try to correct torrent file...' >> "${dir}/log"
	cd "$(dirname $0)"
	"${2}" ./correct.php ${1} "${6}" >> "${dir}/errors"
	last=$?
	if [ $last -eq 0 ] ; then
		echo 'Done.' >> "${dir}/log"
	else
		echo 'Error.' >> "${dir}/log"
	fi
fi
echo $last > "${dir}/status"
chmod a+r "${dir}/status"