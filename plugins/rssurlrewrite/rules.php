<?php

require_once( dirname(__FILE__).'/../../php/cache.php');
require_once( dirname(__FILE__).'/../../php/util.php');
require_once( dirname(__FILE__).'/../../php/settings.php');

class rURLRewriteRule
{
	public $name;
	public $pattern;
	public $replacement;
	public $enabled;
	public $rssHash;
	public $hrefAsSrc;
	public $hrefAsDest;

	public function	__construct( $name, $pattern = '', $replacement = '', $enabled = 0, $rssHash = '', 
		$hrefAsSrc = 0, $hrefAsDest = 0 )
	{
		$this->name = $name;
		$this->pattern = $pattern;
		$this->replacement = $replacement;
		$this->enabled = $enabled;
		$this->rssHash = $rssHash;
		$this->hrefAsSrc = $hrefAsSrc;
		$this->hrefAsDest = $hrefAsDest;
	}
	public function isApplicable( $rss, $groups )
	{
		return(($this->enabled==1) && 
			(!$this->rssHash || (strlen($this->rssHash)==0) || ($this->rssHash==$rss->hash) || $groups->hashPresent( $this->rssHash, $rss->hash )));
	}
        public function apply( &$href, &$guid )
	{
		$src = $this->hrefAsSrc ? $href : $guid;
		$dst = @preg_replace($this->pattern,$this->replacement,$src);
		if(($dst!==false) && ($dst!=$src))
		{
			if($this->hrefAsDest)
				$href = $dst;
			else 				
				$guid = $dst;
		}
		return($dst);
	}
}

class rURLRewriteRulesList
{
	public $hash = "urlrewriterules.dat";
        public $lst = array();

	static public function load( $mngr = null )
	{
		$cache = new rCache();
		$ar = new rURLRewriteRulesList();
		$cache->get($ar);
		if(rTorrentSettings::get()->isPluginRegistered("rss"))
		{
			$changed = false;
			if(is_null($mngr))
			{
				require_once( dirname(__FILE__).'/../rss/rss.php' );
				$mngr = new rRSSManager();	
			}
			foreach($ar->lst as $rule)
			{
				if(!empty($rule->rssHash) &&
					!$mngr->rssList->isExist($rule->rssHash) &&
					!$mngr->groups->get( $rule->rssHash ))
				{
					$rule->rssHash = '';
					$changed = true;
				}
			}
			if($changed)
				$ar->store();
		}			
		return($ar);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	public function add( $filter )
	{
		$this->lst[] = $filter;
	}
	protected static function sortByName( $a, $b )
	{
		return(strcmp($a->name, $b->name));
	}
        public function set()
	{
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		$this->lst = array();
		$rule = null;
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = explode('&', $HTTP_RAW_POST_DATA);
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				if($parts[0]=="name")
				{
					if($rule)
						$this->lst[] = $rule;
					$rule = new rURLRewriteRule(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="pattern")
				{
					if($rule)
						$rule->pattern = trim(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="replacement")
				{
					if($rule)
						$rule->replacement = trim(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="enabled")
				{
					if($rule)
						$rule->enabled = intval($parts[1]);
				}
				else
				if($parts[0]=="no")
				{
					if($rule)
						$rule->no = intval($parts[1]);
				}
				else
				if($parts[0]=="hash")
				{
					if($rule)
						$rule->rssHash = $parts[1];
				}
				else
				if($parts[0]=="hrefAsSrc")
				{
					if($rule)
						$rule->hrefAsSrc = intval($parts[1]);
				}
				else
				if($parts[0]=="hrefAsDest")
				{
					if($rule)
						$rule->hrefAsDest = intval($parts[1]);
				}
  	                }
			if($rule)
				$this->lst[] = $rule;
			usort($this->lst, array(__CLASS__,"sortByName"));
			$this->store();
		}
	}
	public function getContents()
	{
		return($this->lst);
	}
	public function apply( $rss, $groups, &$href, &$guid )
	{
		foreach( $this->lst as $item )
		{
			if($item->isApplicable( $rss, $groups ))
				$item->apply( $href, $guid );
		}
	}
}
