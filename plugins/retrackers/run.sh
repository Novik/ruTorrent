#!/bin/sh
cd `dirname $0`
$1 update.php $2 $3 > /dev/null 2>&1 &