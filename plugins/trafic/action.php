<?php
	require_once( './ratios.php' );
		
	CachedEcho::send(getRatiosStat(),"application/javascript");
