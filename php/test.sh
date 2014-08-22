#!/bin/sh

if [ $# -gt 2 ]; then
	if test -x "$3" && test -f "$3"; then
		> "$2$1.found"
		chmod 0666 "$2$1.found"
	fi
	exit 0
fi

ifs_save="$IFS"; IFS=":"
for dir in $PATH
do
	if test -x "${dir}/$1" && test -f "${dir}/$1"; then
		> "$2$1.found"
		chmod 0666 "$2$1.found"
		break
	fi
done
IFS="$ifs_save"
