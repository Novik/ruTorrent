#!/bin/bash
COMPOSER="./composer.phar"
if command -v composer >/dev/null 2>&1
then
	COMPOSER="composer"
elif [ -f composer.phar ]
then
	wget https://github.com/composer/composer/releases/download/2.3.7/composer.phar
	chmod +x composer.phar
fi
$COMPOSER install
