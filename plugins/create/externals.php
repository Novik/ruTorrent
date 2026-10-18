<?php

// $useExternal names both the wrapper script in this directory and, by
// default, the program that wrapper runs. Map the backends whose program is
// spelled differently from the backend itself.

function createExternalEXE( $external )
{
	$programs = array(
		'transmissioncreate' => 'transmission-create',
	);
	return( isset($programs[$external]) ? $programs[$external] : $external );
}
