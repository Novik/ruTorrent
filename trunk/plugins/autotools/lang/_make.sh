#!/bin/sh

for lang in br cn cs da de es fi fr hu it lv nl pl sk uk sr tr ; do

	cp -f ./en.js $lang".js"
	chmod 644 $lang".js"
	chown www:www $lang".js"

done

