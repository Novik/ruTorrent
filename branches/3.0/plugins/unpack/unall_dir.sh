#!/bin/sh
#
# $1 - unrar
# $2 - intput directory with tail slash
# $3 - output directory with tail slash
# $4 - log file name
# $5 - status file name
# $6 - unzip

ret=0
"$(dirname $0)"/unrar_dir.sh "$1" "$2" "$3" "$4" dummy
last=$?
[ $last -gt 1 ] && ret=$last
"$(dirname $0)"/unzip_dir.sh "$6" "$2" "$3" "$4" dummy
last=$?
[ $last -gt 1 ] && ret=$last

echo $ret > "$5"
chmod a+r "$5"
chmod a+r "$4"

