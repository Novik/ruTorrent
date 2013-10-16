#!/bin/sh
#
# $1 - users profile
# $2 - pid
# $3 - hash
# $4 - base_path
# $5 - is_multi_file

echo "$4" >> "$1"/"$2".tmp
echo "$5" >> "$1"/"$2".tmp
mv -f "$1"/"$2".tmp "$1"/"$3".list