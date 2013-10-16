#!/bin/sh
#
# $1 - taskNo
# $2 - php
# $3 - mktorrent
# $4 - path
# $5 - piecesize
# $6 - user
# $7 - tmp

dir="${7}${6}${1}"
mkdir "${dir}"
chmod a+rx "${dir}"
echo $$ > "${dir}/pid"
chmod a+r "${dir}/pid"
"${3}" -v -l ${5} -a dummy -o "${dir}/temp.torrent" "${4}" 2> "${dir}/errors" > "${dir}/log"
last=$? 
chmod a+r "${dir}/*"
if [ $last -eq 0 ] ; then
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