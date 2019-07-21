<?php

if(empty($pathToExternals['mediainfo']))	// May be path already defined?
{
	$pathToExternals['mediainfo'] = '';	// Something like /usr/bin/mediainfo. If empty, will be found in PATH.
}
