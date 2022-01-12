<?php

require_once( dirname(__FILE__).'/../_task/task.php' );
require_once( dirname(__FILE__).'/../../php/Torrent.php' );
require_once( dirname(__FILE__).'/../../php/rtorrent.php' );
eval( FileUtil::getPluginConf( 'create' ) );

class recentTrackers
{
	public $hash = "rtrackers.dat";
	public $list = array();

	static public function load()
	{
		$cache = new rCache();
		$rt = new recentTrackers();
		$cache->get($rt);
		return($rt);
	}
	public function store()
	{
		$cache = new rCache();
		$this->strip();
		return($cache->set($this));
	}
	public function delete($trk)
	{
		$cache = new rCache();
		$this->list = $trk;
		return($cache->set($this));
	}
	public function get()
	{
		$ret = array();
		foreach( $this->list as $ann )
			$ret[self::getTrackerDomain($ann)] = $ann;
		return($ret);
	}
	public function strip()
	{
		global $recentTrackersMaxCount;
		$this->list = array_values( array_unique($this->list) );
		$cnt = count($this->list);
		$arr = array_values($this->list);
		$lastAnn = self::getTrackerDomain(end($arr));
		$i = 0;
		foreach( $this->list as $ann )
		{
			if( ($i + 1) === $cnt )
				break;
			if( self::getTrackerDomain($ann) === $lastAnn )
				array_splice($this->list,$i,1);
			$i = $i + 1;
		}
		$cnt = $cnt-$recentTrackersMaxCount;
		if($cnt>0)
			array_splice($this->list,0,$cnt);
	}
	static public function getTrackerDomain($announce)
	{
		$domain = parse_url($announce,PHP_URL_HOST);
		if($domain && (preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/",$domain)!=1))
		{
			$parts = explode('.',$domain);
			$cnt = count($parts);
			if($cnt>2)
			{
				if(in_array( $parts[$cnt-2], array( "co", "com", "net", "org" ) ) ||
					in_array( $parts[$cnt-1], array( "uk" ) ))
					$parts = array_slice($parts, $cnt-3);
				else
					$parts = array_slice($parts, $cnt-2);
				$domain = implode('.',$parts);
			}
		}
		return(empty($domain) ? 'invalid domain' : $domain);
	}
}

$ret = array();
if(isset($_REQUEST['cmd']))
{
	switch($_REQUEST['cmd'])
	{
		case "rtget":
		{
			$rt = recentTrackers::load();
			$ret = $rt->get();
			break;
		}
		case "rtdelete":
		{
			if(isset($_REQUEST['trackers']))
			{
				$rt = recentTrackers::load();
				$trk = array();
				$arr = explode("\r",$_REQUEST['trackers']);
				foreach( $arr as $key => $value )
				{
					$value = trim($value);
					if(strlen($value))
						$trk[] = $value;
				}
				$newList = array_diff($rt->list,$trk);
				if( $newList !== $rt->list )
					$ret = $rt->delete($newList);
			}
			break;
		}
		case "create":
		{
			$error = "Invalid parameters";
		        if(isset($_REQUEST['path_edit']))
		        {
		        	$path_edit = trim($_REQUEST['path_edit']);
				if(is_dir($path_edit))
					$path_edit = FileUtil::addslash($path_edit);
		        	if(rTorrentSettings::get()->correctDirectory($path_edit))
				{
					$rt = recentTrackers::load();
					$trackers = array();
					$announce_list = '';
					if(isset($_REQUEST['trackers']))
					{
						$arr = explode("\r",$_REQUEST['trackers']);
						foreach( $arr as $key => $value )
						{
							$value = trim($value);
							if(strlen($value))
							{
								$trackers[] = $value;
								$rt->list[] = $value;
                                                        }
                                                        else
							{
								if(count($trackers)>0)
								{
									$announce_list .= (' -a '.escapeshellarg(implode(',',$trackers)));
									$trackers = array();
								}
							}
						}
					}
					$rt->store();
					if(count($trackers)>0)
						$announce_list .= (' -a '.escapeshellarg(implode(',',$trackers)));
					$piece_size = 262144;
					if(isset($_REQUEST['piece_size']))
						$piece_size = $_REQUEST['piece_size']*1024;
	       				if(!$pathToCreatetorrent || ($pathToCreatetorrent==""))
						$pathToCreatetorrent = $useExternal;
					if($useExternal=="mktorrent")
						$piece_size = log($piece_size,2);
					if(isset($_REQUEST['hybrid']))
						$hybrid = TRUE;
					if($useExternal===false)
						$useExternal = "inner";
					$task = new rTask( array
					(
						'arg' => FileUtil::getFileName($path_edit),
						'requester'=>'create',
						'name'=>'create',
						'path_edit'=>$_REQUEST['path_edit'],
						'trackers'=>$_REQUEST['trackers'],
						'comment'=>$_REQUEST['comment'],
						'source'=>$_REQUEST['source'],
						'start_seeding'=>$_REQUEST['start_seeding'],
						'piece_size'=>$_REQUEST['piece_size'],
						'private'=>$_REQUEST['private'],
						'hybrid'=>$_REQUEST['hybrid']
					) );
					$commands = array();

					$commands[] = escapeshellarg($rootPath.'/plugins/create/'.$useExternal.'.sh')." ".
					$task->id." ".
					escapeshellarg(Utility::getPHP())." ".
					escapeshellarg($pathToCreatetorrent)." ".
					escapeshellarg($path_edit)." ".
					$piece_size." ".
					escapeshellarg(User::getUser())." ".
					escapeshellarg(rTask::formatPath($task->id))." ".
					escapeshellarg($hybrid);

					$commands[] = '{';
					$commands[] = 'chmod a+r "${dir}"/result.torrent';
					$commands[] = '}';
					$ret = $task->start($commands, 0);
					break;
				}
				else
					$error = 'Incorrect directory ('.mb_substr($path_edit,mb_strlen($topDirectory)-1).')';
			}
			$ret = array( "no"=>-1, "pid"=>0, "status"=>255, "log"=>array(), "errors"=>array($error) );
			break;
		}
		case "getfile":
		{
			$dir = rTask::formatPath( $_REQUEST['no'] );
			$torrent = new Torrent( $dir."/result.torrent" );
			if( !$torrent->errors() )
				$torrent->send();
			else
				header('HTTP/1.0 404 Not Found');
			exit();
		}
	}
}

CachedEcho::send(JSON::safeEncode($ret),"application/json");
