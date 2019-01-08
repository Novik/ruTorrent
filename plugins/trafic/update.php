<?php
	$path = dirname(realpath($argv[0]));
	if(chdir($path))
	{
		if( count( $argv ) > 1 )
			$_SERVER['REMOTE_USER'] = $argv[1];
		require_once( dirname(__FILE__).'/../../php/xmlrpc.php' );
		require_once( 'stat.php' );
		eval(getPluginConf('trafic'));
		
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand("get_up_total"),
			new rXMLRPCCommand("get_down_total"), 
			new rXMLRPCCommand("d.multicall", array("main",getCmd("d.get_hash="),getCmd("d.get_up_total="),getCmd("d.get_down_total=")))));
		$req->setParseByTypes();
		if($req->run() && !$req->fault)
		{
		        $dir = getSettingsPath().'/trafic/';
			$was = array(0,0,0);
			$wasTorrents = array();
			if($file=@fopen($dir.'last.csv',"r"))
			{
				$was = fgetcsv($file,100);
				while(($data = fgetcsv($file, 1000)) !== false)
					$wasTorrents[$data[0]] = array_slice($data,1);
				fclose($file);
			}
			$tm = time();
			$needUpdate = ($was[2]+3600>=$tm);
			$now = array_slice($req->i8s,0,2);
			$now[2] = $tm;
			$nowTorrents = array();
			for($i = 0; $i<count($req->strings); $i++)
				$nowTorrents[$req->strings[$i]] = array_slice($req->i8s,($i+1)*2,2);
			ksort($nowTorrents);

			$randName = getTempFilename('trafic');
			if($file=@fopen($randName,"w"))
			{
				if( ($ok = fputcsv($file,$now))!==false )
				{
					foreach($nowTorrents as $key=>$data)
					{
						$tmp = $data;
						array_unshift($tmp, $key);
						if( ($ok = fputcsv($file,$tmp))===false )
							break;
					}
				}					
				if($ok !== false)
				{
					if( fclose($file) !== false )
					{
						rename($randName,$dir.'last.csv');
						@chmod($dir.'last.csv',$profileMask & 0666);
					}
					else
						unlink($randName);
				}
				else
					unlink($randName);
			}
			if($needUpdate)
			{
				$needTorrents = array();
                                foreach($nowTorrents as $key=>$data)
				{
					if(array_key_exists($key,$wasTorrents))
					{
						$delta_up = floatval($data[0])-floatval($wasTorrents[$key][0]);
						$delta_down = floatval($data[1])-floatval($wasTorrents[$key][1]);
						if(($delta_up<0) || ($delta_down<0))
						{
							$delta_up = 0;
							$delta_down = 0;
						}
						if($delta_down!=0 || $delta_up!=0)
						{
							$needTorrents[$key] = array($delta_up,$delta_down);
							if($collectStatForTorrents)
							{
								$st = new rStat("torrents/".$key.".csv");
								$st->correct($delta_up,$delta_down);
								$st->flush();
							}
						}
					}
					else
						$needTorrents[$key] = $data;
				}
				$trackers = array();
				foreach($needTorrents as $key=>$data)
				{
				        $req = new rXMLRPCRequest( array(
						new rXMLRPCCommand("t.multicall", 
							array($key,"",getCmd("t.is_enabled="),getCmd("t.get_type="),getCmd("t.get_group="),getCmd("t.get_url=")))));
					$req->setParseByTypes();
					if($req->run() && !$req->fault)
					{
						$checkedDomains = array();
						$lastGroup = 65535;
						for($i = 0; $i<count($req->strings); $i++)
						{
							if($req->i8s[$i*3+2]>$lastGroup)
								break;
							if(($req->i8s[$i*3]!=0) && ($req->i8s[$i*3+1]<3))
							{
								$lastGroup = $req->i8s[$i*3+2];
								$domain = parse_url($req->strings[$i],PHP_URL_HOST);
								if(preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/",$domain)!=1)
								{
									$pos = strpos($domain,'.');
									if($pos!==false)
									{
										$tmp = substr($domain,$pos+1);
										if(strpos($tmp,'.')!==false)
											$domain = $tmp;
									}
								}
								if(!array_key_exists($domain,$checkedDomains))
								{
	                                                                if(array_key_exists($domain,$trackers))
									{
										$trackers[$domain][0]+=$needTorrents[$key][0];
										$trackers[$domain][1]+=$needTorrents[$key][1];
									}
									else
										$trackers[$domain] = $needTorrents[$key];
									$checkedDomains[$domain] = true;
								}
							}
						}
					}
				}
				$delta_up = floatval($now[0])-floatval($was[0]);
				$delta_down = floatval($now[1])-floatval($was[1]);
				if(($delta_up<0) || ($delta_down<0))
				{
					$delta_up = 0;
					$delta_down = 0;
				}
				$st = new rStat('global.csv');
				$st->correct($delta_up,$delta_down);
				$st->flush();

				$dh = @opendir($dir."trackers");
				if($dh)
				{
					while(false !== ($file = readdir($dh)))
					{
						if(is_file($dir."trackers/".$file))
						{
							$file = basename($file, ".csv");
							if(!array_key_exists($file,$trackers))
								$trackers[$file] = array(0,0);
						}
					}
					closedir($dh);
				}

                                foreach($trackers as $key=>$data)
				{
				        if(!empty($key))
				        {
						$st = new rStat("trackers/".$key.".csv");
						$st->correct($data[0],$data[1]);
						$st->flush();
					}
				}

				if($collectStatForTorrents)
				{
					$existingStats = array();
					$dh = @opendir($dir."torrents");
					if($dh)
					{
						while(false !== ($file = readdir($dh)))
						{
							if(is_file($dir."torrents/".$file))
							{
								$hash = basename($file, ".csv");
								$existingStats[$hash] = filemtime($dir."torrents/".$file);
							}
						}
					}
					closedir($dh);
					$deletedTorrents = array_diff_key( $existingStats, $nowTorrents );
					foreach($deletedTorrents as $hash=>$time)
					{
						if($tm - $time > $storeDeletedTorrentsStatsDuring)
							@unlink($dir."torrents/".$hash.".csv");
					}
				}
			}
		}
	}
