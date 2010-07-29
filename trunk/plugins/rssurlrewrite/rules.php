<?php

require_once( dirname(__FILE__).'/../../php/cache.php');
require_once( dirname(__FILE__).'/../../php/util.php');

class rURLRewriteRule
{
	public $name;
	public $pattern;
	public $replacement;
	public $enabled;
	public $rssHash;
	public $hrefAsSrc;
	public $hrefAsDest;
	public $no = -1;

	public function	rURLRewriteRule( $name, $pattern = '', $replacement = '', $enabled = 0, $rssHash = '', 
		$hrefAsSrc = 0, $hrefAsDest = 0, $no = -1 )
	{
		$this->name = $name;
		$this->pattern = $pattern;
		$this->replacement = $replacement;
		$this->enabled = $enabled;
		$this->rssHash = $rssHash;
		$this->hrefAsSrc = $hrefAsSrc;
		$this->hrefAsDest = $hrefAsDest;
		$this->no = $no;
	}
	public function getContents()
	{
		return("{ name: ".quoteAndDeslashEachItem($this->name).", enabled: ".$this->enabled.", pattern: ".quoteAndDeslashEachItem($this->pattern).", replacement: ".quoteAndDeslashEachItem($this->replacement).
			", hash: ".quoteAndDeslashEachItem($this->rssHash).", hrefAsSrc: ".$this->hrefAsSrc.", hrefAsDest: ".$this->hrefAsDest." }");
	}
	public function isApplicable( $rss )
	{
		return(($this->enabled==1) && 
			(!$this->rssHash || (strlen($this->rssHash)==0) || ($this->rssHash==$rss->hash)));
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

	static public function load()
	{
		$cache = new rCache();
		$ar = new rURLRewriteRulesList();
		$cache->get($ar);
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
						$rule->enabled = $parts[1];
				}
				else
				if($parts[0]=="no")
				{
					if($rule)
						$rule->no = $parts[1];
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
						$rule->hrefAsSrc = $parts[1];
				}
				else
				if($parts[0]=="hrefAsDest")
				{
					if($rule)
						$rule->hrefAsDest = $parts[1];
				}
  	                }
			if($rule)
				$this->lst[] = $rule;
			usort($this->lst, create_function( '$a,$b', 'return(strcmp($a->name, $b->name));'));
			$this->store();
		}
	}
	public function getContents()
	{
		$ret = "[";
		foreach( $this->lst as $item )
		{
			$ret.=$item->getContents();
			$ret.=",";
		}
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return( $ret."]" );
	}
	public function apply( $rss, &$href, &$guid )
	{
		foreach( $this->lst as $item )
		{
			if($item->isApplicable( $rss ))
			{
				$item->apply( $href, $guid );
				break;
			}
		}
	}
}

?>