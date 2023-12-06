<?php

	require_once( 'cpu.php' );
	$cpu = rCPU::load();
	CachedEcho::send('{ "load": '.$cpu->get().' }',"application/json");
