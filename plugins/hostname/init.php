<?php

	if ($theSettings->iVersion >= 0x805) {
		$theSettings->registerPlugin("hostname");
	} else {
		$jResult .= "plugin.disable();";
	}

?>
