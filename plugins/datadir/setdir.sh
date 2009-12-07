#!/bin/sh

cd $(dirname $0)

"$1" setdir.php "$2" "$3" "$4" &

echo "$1 setdir.php \"$2\" \"$3\" \"$4\"" > /tmp/setdir.log

