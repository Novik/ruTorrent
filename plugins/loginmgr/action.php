<?php
require_once( "accounts.php" );

$em = accountManager::load();
if($em===false)
{
	$em = new accountManager();
	$em->obtain("./accounts");
}

if(isset($_REQUEST['mode']))
{
	$cmd = $_REQUEST['mode'];
	switch($cmd)
	{
		case "set":
		{
			$notRetired = array();
			if(!$em->set($notRetired))
			{
				FileUtil::toLog('loginmgr: account-save-failed');
				http_response_code(500);
				CachedEcho::send('Account settings could not be saved.','text/plain');
			}
			// The credentials are saved and stay saved -- they are what the
			// next login uses, and that login replaces the session. What
			// cannot stand is answering with the settings as though the old
			// session had gone: it is still being sent to the tracker.
			if(count($notRetired))
			{
				http_response_code(500);
				CachedEcho::send('Account settings were saved, but a previous session could not be discarded.','text/plain');
			}
			CachedEcho::send($em->get(),"application/javascript");
			break;
		}
		case "info":
		{
			CachedEcho::send(JSON::safeEncode($em->getInfo()),"application/json");
			break;
		}
	}
}
