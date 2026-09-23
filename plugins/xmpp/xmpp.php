<?php

require_once( dirname(__FILE__)."/../../php/settings.php");
require_once( dirname(__FILE__)."/../../php/utility/json.php");

class rXmpp
{
	public $hash = "xmpp.dat";
	public $modified = false;
	public $jabberHost = "";
	public $jabberPort = 5222;
	public $jabberLogin = "";
	public $jabberServer = "";
	public $jabberPasswd = "";
	public $useEncryption = 1;
	public $advancedSettings = 0;
	public $jabberFor = "";
	protected $message_templ = "Torrent '{TORRENT}' has been downloaded.";
	public $message = "";

	static public function load()
	{
		$cache = new rCache();
		$at = new rXmpp();
		$cache->get( $at );
		return $at;
	}
	public function store()
	{
		$cache = new rCache();
		return $cache->set( $this );
	}

	// $rawPostData names the request body, which is otherwise read from
	// php://input and so cannot be handed to this from a test.
	public function set($rawPostData = null)
	{
		if( !isset( $HTTP_RAW_POST_DATA ) )
			$HTTP_RAW_POST_DATA = is_null($rawPostData) ? file_get_contents( "php://input" ) : $rawPostData;
		if( isset( $HTTP_RAW_POST_DATA ) )
		{
			$vars = explode( '&', $HTTP_RAW_POST_DATA );
			// Said first by a settings page that escaped its values, and by no
			// page before that one. It has to open the body: an old page wrote
			// values literally, so an ampersand in one can make the text after
			// it look like another field. Letting such a later field opt the
			// whole body into decoding would reinterpret unrelated legacy
			// values. Nothing is unescaped without the opening word: what
			// arrived is the value.
			$escaped = isset($vars[0]) && ($vars[0] === "formEncoding=percent-v1");
			// The settings page is not shown the stored password, so it has
			// none to send back unless somebody typed one. A request that
			// carries no jabberPasswd leaves the stored one alone.
			$storedPasswd = $this->jabberPasswd;
			$passwdWasSent = false;
			$this->jabberHost = "";
			$this->jabberPort = 5222;;
			$this->jabberLogin = "";
			$this->jabberServer = "";
			$this->jabberPasswd = "";
			$this->useEncryption = 1;
			$this->advancedSettings = 0;
			$this->jabberFor = "";
			$this->message = $this->message_templ;
			foreach( $vars as $var )
			{
				// An escaped value went out through encodeURIComponent() and
				// comes back through the inverse of that one: a space
				// travels as "%20" and a plus as "%2B", which leaves a
				// plus in the body a plus. Splitting at the first "="
				// only: what follows one is part of the value.
				$parts = explode( "=", $var, 2 );
				$name = $parts[0];
				$value = "";
				if( isset($parts[1]) )
					$value = $escaped ? rawurldecode($parts[1]) : $parts[1];
				if( $name == "jabberHost" )
				{
					$jabberHost = $value;
				}
				else if( $name == "jabberPort" )
				{
					$jabberPort = $value;
				}
				else if( $name == "jabberJid" )
				{
					// One field on the wire, so it is unescaped before it
					// is split, and at the first "@" only: the localpart
					// of a jid cannot hold one, and a later one belongs to
					// the server half rather than being dropped.
					$jid = explode( "@", $value, 2 );
					$this->jabberLogin = $jid[0];
					$this->jabberServer = count($jid) > 1 ? $jid[1] : "";
				}
				else if( $name == "jabberPasswd" )
				{
					$passwdWasSent = true;
					$this->jabberPasswd = $value;
				}
				else if( $name == "useEncryption" )
				{
					$useEncryption = $value;
				}
				else if( $name == "advancedSettings" )
				{
					$this->advancedSettings = $value;
				}
				else if ( $name == "jabberFor" )
				{
					$this->jabberFor = $value;
				}
				else if ( $name == "message" )
				{
					if ($value)
					{
					    $this->message = $value;
					}
				}
			}
			if (!$passwdWasSent)
			{
			    $this->jabberPasswd = $storedPasswd;
			}
			if ($this->advancedSettings)
			{
			    if ($jabberHost)
			    {
				$this->jabberHost = $jabberHost;
			    }
			    if ($jabberPort)
			    {
				$this->jabberPort = $jabberPort;
			    }
			    $this->useEncryption = $useEncryption;
			}
			$this->setHandlers();
		}
		$this->store();
	}

	public function get()
	{
		$jid = "";
		if ($this->jabberLogin && $this->jabberServer)
		{
		    $jid = $this->jabberLogin."@".$this->jabberServer;
		}
		return "theWebUI.xmpp = ".JSON::jsValue(array(
			"JabberHost" => strval($this->jabberHost),
			"JabberPort" => intval($this->jabberPort),
			"JabberJID" => $jid,
			// Whether a password is stored, never the password. This is
			// appended to the javascript of every page load by
			// plugins/xmpp/init.php, and is the whole answer of
			// plugins/xmpp/action.php.
			"JabberPasswd_set" => (strval($this->jabberPasswd)==="") ? 0 : 1,
			"UseEncryption" => intval($this->useEncryption),
			"AdvancedSettings" => intval($this->advancedSettings),
			"JabberFor" => strval($this->jabberFor),
			"Message" => strval($this->message ? $this->message : $this->message_templ)
		)).";\n";
	}

	public function setHandlers()
	{
		$theSettings = rTorrentSettings::get();
		$pathToXmpp = dirname(__FILE__);
		$req = new rXMLRPCRequest();
		if ( $this->message !== '' && isset($this->jabberServer) && isset($this->jabberLogin) && isset($this->jabberPasswd) && isset($this->jabberFor))
		{
		    $cmd = $theSettings->getOnFinishedCommand(array('xmpp'.User::getUser(),
			    getCmd('execute.nothrow').'={sh,-c,\"$0\" \"$@\" </dev/null >/dev/null 2>&1 &,'.Utility::getPHP().','.$pathToXmpp.'/notify.php,"$'.getCmd('d.name').'=","'.User::getUser().'"}'
			    ));
		}
		else
		    $cmd = $theSettings->getOnFinishedCommand(array('xmpp'.User::getUser(), getCmd('cat=')));
		$req->addCommand($cmd);
		return($req->success());
	}
}
