<?php

	require_once( 'cpu.php' );
	$cpu = rCPU::load();
	cachedEcho('{ "load": '.$cpu->get().' }',"application/json");
