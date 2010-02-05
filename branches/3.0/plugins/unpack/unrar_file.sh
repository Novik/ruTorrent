#!/bin/sh
#
# $1 - unrar
# $2 - archive
# $3 - output directory with tail slash
# $4 - log file name
# $5 - status file name
# $6 - addition keys (-v)

"$1" x -ierr -idp -kb -o+ -p- -y $6 -- "$2" "$3" 2> "$4"
echo $? > "$5"
chmod a+r "$4"
chmod a+r "$5"

