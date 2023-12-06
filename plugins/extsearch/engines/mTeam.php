<?php

class mTeamEngine extends commonEngine
{

	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'Tout'=>'' );

	protected static function disableEntityLoader()
	{
		if( function_exists('libxml_disable_entity_loader') )
		{
			libxml_disable_entity_loader( true );
		}
	}

	protected static function getDate($ago)
	{
		$date = date_create()->modify('-'.$ago.' days')->format('Y-m-d');
		return($date);
	}

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		$client->referer = $url;
		return($client);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$url = 'http://mteam.fr';
		if($useGlobalCats)
			$categories = array( 'all'=>'' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		self::disableEntityLoader();

		$cli = $this->fetch($url . '/home.php?ref=search&sub=menu&value=' . $what);
		if (($cli == false) || (strpos($cli->results, "Les www du lien mteam.fr ne sont plus valides.") !== false))
			return;

		$doc = new DOMDocument();
		@$doc->loadHTML($cli->results);

		foreach ($doc->getElementsByTagName('table') as $table) {
			foreach ($table->getElementsByTagName('tr') as $tr) {
				$tds = $tr->getElementsByTagName('td');
				if ($tds->length != 6) continue; //bail if table rows isn't as expected

				try {
					$item = $this->getNewEntry();

					$link = $tds[5]->getElementsByTagName('a')[0];
					if (!$link) continue; //bail if no download link found
					$link = $link->getAttribute('href');
					if (substr( $link, 0, 2 ) !== "/a") continue; //bail if download link isn't a .torrent file
					$link = $url . $link;

					$item["cat"] = "Jeux PC > ".$tds[0]->getElementsByTagName('img')[0]->getAttribute('title');
					$item["desc"] = $url . $tds[1]->getElementsByTagName('a')[0]->getAttribute('href');
					$item["name"] = $tds[1]->getElementsByTagName('span')[0]->getAttribute('title');
					$item["size"] = self::formatSize($tds[4]->textContent);
					$ago_match = substr($tds[2]->textContent, 0, -1);
					$time = strtotime(self::getDate($ago_match));
					$item["time"] = $time + 1;

					$ret[$link] = $item;
				} catch (Exception $e) {
					//table row wasn't in the correct format
				}
			}
		}
	}
}
