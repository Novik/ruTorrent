<?php
	if ( $theSettings->iVersion >= 0x805 )
		$theSettings->registerPlugin( "chunks" );
	else
		$jResult .= "plugin.disable();";
?>
