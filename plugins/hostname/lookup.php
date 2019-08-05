<?php

	$IP = $_POST[ "ip" ];
	if (!isset($IP)) {
		$Result = null;
	} else {
		if (function_exists('filter_var')) {
			$IP = filter_var($IP, FILTER_VALIDATE_IP);   
		}
		$Result = $IP . "<|>" . gethostbyaddr($IP);
	}

	echo($Result);
?>
