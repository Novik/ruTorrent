#!/bin/sh
#
# $1 - unrar
# $2 - archive
# $3 - output directory with tail slash

"$1" x -ai -c- -kb -o+ -p- -y -v -- "$2" "$3"

