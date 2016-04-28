#!/bin/bash

# This script will quickly and easily swap between two different configurations so that access to configuration
# options can be easily turned on or off, this can help to protect against unauthorised setting changes.

STR=$(cat access.ini | head -n1| cut -c4)
GOOD=$(cat access_yes)
BAD=$(cat access_no)

echo $STR

if [ $STR = 0 ]
then
        echo -e "\x1B[31m Access Opened \x1B[0m"
        echo "$GOOD" > access.ini
else
        echo -e "\x1B[32m Access Closed \x1B[0m"
        echo "$BAD" > access.ini
fi