<?php
class RARbgTorrentAPIEngine extends commonEngine
{
    //This is for YTS.ag / yify movies. So categorie is just Video. No need to any categories.
	public $defaults = array( "public"=>true, "page_size"=>75 );
	public $categories = array(
		"all"=>"0", "XXX (18+)"=>"4","Movies/XVID"=>"14","Movies/XVID/720"=>"48","Movies/x264"=>"17","Movies/x264/1080"=>"44","Movies/x264/720"=>"45","Movies/x264/3D"=>"47","Movies/x264/4k"=>"50","Movies/x265/4k"=>"51","Movs/x265/4k/HDR"=>"52","Movies/Full BD"=>"42","Movies/BD Remux"=>"46","TV Episodes"=>"18","TV HD Episodes"=>"41","TV UHD Episodes"=>"49","Music/MP3"=>"23","Music/FLAC"=>"25","Games/PC ISO"=>"27","Games/PC RIP"=>"28","Games/PS3"=>"40","Games/XBOX-360"=>"32","Software/PC ISO"=>"33");
    
    public function get_token(){
        $tokenurl='https://torrentapi.org/pubapi_v2.php?get_token=get_token';
        $json = file_get_contents($tokenurl);
        $obj = json_decode($json);
        return $obj->token;
    }

	public function action($what,$cat,&$ret,$limit,$useGlobalCats){
	    $token = self::get_token();

        $added = 0;
        $url="https://torrentapi.org/pubapi_v2.php?token=$token&format=json_extended&mode=search&search_string=$what&category=$cat";

        $json = file_get_contents($url);
        $obj = json_decode($json);
        $torrent_count = count($obj->torrent_results);
        for($i=0;$i<$torrent_count;$i++){
            $torrent = $obj->torrent_results[$i];
            if (!array_key_exists($torrent->download, $ret)) {
                $item = $this->getNewEntry();
                $item["cat"] = $torrent->category;
                $item["desc"] = $torrent->info_page;
                $item["name"] = $torrent->title;
                $item["size"] = $torrent->size;
                $item["seeds"] = $torrent->seeders;
                $item["peers"] = $torrent->leechers;
                $ret[$torrent->download] = $item;
                $added++;
        	    if ($added >= $limit)
        	        return;
            }
        }    
    }
	
}
