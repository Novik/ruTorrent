#!/bin/bash

# Makes CSS file with links to flag pictures
# Quick and dirty, scans only local "flags" directory

if [ -f geoip.css ]; then
    cp geoip.css geoip.css.bak
    echo "Copied geoip.css  to  geoip.css.bak."
fi
> geoip.css

if [ ! -d flags ]; then
    echo "Directory with flags does not exist."
    exit 1
fi

for fl in `ls -1 flags/*[Gg][Ii][Ff]`
do
    # Remove prefix and suffix, we need only country code
    cnt=${fl/\/*\//}
    cnt=${cnt/.*/}
    cnt=${cnt#*/}
    echo ".geoip_flag_"$cnt" {background: transparent url( \""$fl"\" ) no-repeat center center; width: 22px; }" >> geoip.css
    echo ".iegeoip_flag_"$cnt" {background: transparent url( \""$fl"\" ) no-repeat scroll left
top; padding-left: 20px; }" >> geoip.css
done

