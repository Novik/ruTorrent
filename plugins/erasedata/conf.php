<?php

	// interval for schedule command of garbage checker in seconds
	$garbageCheckInterval = 15;
	$enableForceDeletion = false;

	$erasedebug_enabled = false;

  // The maximum amount of times a download is attempted to be marked for erasure before it is skipped,
  // Setting a limit here prevents a problematic case being retried indefinitely and blocking other RPC calls.
  //
  // 0 disables the limit and will retry indefinitely.
	$erasePendingMaxAttempts = 10;

	// Replaces "remove" option in torrent menu to always delete with data
	// Refrains from showing other options on web interface
	$replaceRemoveTorrent = false;
