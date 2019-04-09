<?php

if(empty($pathToExternals['python']))	// May be path already defined?
{
	$pathToExternals['python'] = '';// Something like /usr/bin/python. If empty, will be found in PATH.
}
