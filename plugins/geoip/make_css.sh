#!/bin/bash

# Makes CSS file with links to flag pictures
# Quick and dirty, scans only local "flags" directory
#
# cd geoip && ./make_css.sh

if [ -f geoip.css ]; then
    cp geoip.css geoip.css.bak
    echo "Copied geoip.css  to  geoip.css.bak"
fi
# Truncate file
> geoip.css

if [ ! -d flags ]; then
	echo "Directory flags does not exist"
	exit 1
fi

echo ".geoip {background-repeat: no-repeat; background-position: center center; width: 22px; }" >> geoip.css

for fl in `ls -1 flags/*[Gg][Ii][Ff]`
do
    # Remove prefix and suffix, we need only country code
    cnt=${fl/\/*\//}
    cnt=${cnt/.*/}
    cnt=${cnt#*/}
    echo ".geoip_flag_"$cnt" {background-image: url( \""$fl"\" ); }" >> geoip.css
done

