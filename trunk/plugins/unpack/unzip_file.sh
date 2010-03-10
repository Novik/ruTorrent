#!/bin/sh
#
# $1 - unzip
# $2 - archive
# $3 - output directory with tail slash
# $4 - log file name
# $5 - status file name

mkdir -p "$3"
"$1" -o "$2" -d "$3" > /dev/null 2>> "$4"
echo $? > "$5"
chmod a+r "$4"
chmod a+r "$5"

