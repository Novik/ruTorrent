<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * A command rtorrent answers to has more than one name. php/methods-*.php is
 * this tree's own statement of which spelling a given daemon takes: each entry
 * maps the name a caller may write to the name the daemon registers, and
 * rXMLRPCCommand puts every call through it.
 *
 * $denyPrefixes names one spelling of each refused family. An alias whose
 * target it refuses is the same command under the name an older daemon
 * registers, so refusing one and not the other refuses nothing on that daemon
 * — and below 0.16.10 the untrusted header is read and ignored, so this list
 * is the only refusal there is.
 *
 * Enumerated from the alias tables rather than from a list written here, so a
 * spelling added later that lands on a refused command fails this file.
 */
class XMLRPCProxyLegacySpellingHolder
{
	public $aliases = array();

	public function load($file)
	{
		require($file);
	}
}

class XMLRPCProxyLegacySpellingTest extends TestCase
{
	/**
	 * Every alias in the tree as name => registered name, with the file it
	 * came from, so a failure says where to look.
	 */
	private function aliases()
	{
		$all = array();
		$files = glob(__DIR__ . '/../../php/methods-*.php');
		sort($files);
		foreach($files as $file)
		{
			$holder = new XMLRPCProxyLegacySpellingHolder();
			$holder->load($file);
			foreach($holder->aliases as $name => $info)
			{
				$target = (is_array($info) && isset($info['name']))
					? $info['name'] : (string)$info;
				$all[] = array(
					'file'   => basename($file),
					'name'   => $name,
					'target' => $target,
				);
			}
		}
		return $all;
	}

	private function call($method, $params = array())
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . $method
			. '</methodName><params>';
		foreach($params as $p)
			$xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
				. '</string></value></param>';
		$xml .= '</params></methodCall>';
		return $xml;
	}

	private function decide($xml)
	{
		return XMLRPCProxy::decide($xml, 'sanitize',
			array('d.custom1.set', 'd.directory.set'), false,
			array('directory' => array('root' => '/', 'resolve' => null)));
	}

	public function testTheAliasTablesAreReadable()
	{
		$aliases = $this->aliases();
		$this->assertTrue(count($aliases) > 300,
			'the alias tables were loaded (' . count($aliases) . ' entries)');
	}

	/**
	 * The refusal follows the command, not the spelling.
	 */
	public function testAnAliasOfARefusedCommandIsRefusedUnderItsOwnName()
	{
		$missed = array();
		foreach($this->aliases() as $alias)
		{
			if(XMLRPCProxy::refusedCommandName($alias['target']) === null)
				continue;
			if(XMLRPCProxy::refusedCommandName($alias['name']) !== null)
				continue;
			$missed[] = $alias['file'] . ': ' . $alias['name']
				. ' -> ' . $alias['target'];
		}
		$this->assertTrue(count($missed) === 0,
			'every alias of a refused command is refused under its own name'
			. (count($missed) ? ', missed: ' . implode('; ', $missed) : ''));
	}

	/**
	 * The same question at the raw-XMLRPC door: a call whose method name is an
	 * alias of a refused command is refused there too, rather than forwarded
	 * for a header older daemons ignore.
	 */
	public function testTheRawDoorRefusesAnAliasOfARefusedCommand()
	{
		$forwarded = array();
		foreach($this->aliases() as $alias)
		{
			if(XMLRPCProxy::refusedCommandName($alias['target']) === null)
				continue;
			$decision = $this->decide($this->call($alias['name'], array('x')));
			if($decision['action'] !== 'reject')
				$forwarded[] = $alias['name'] . ' (' . $alias['file'] . ')';
		}
		$this->assertTrue(count($forwarded) === 0,
			'the raw door rejects a call naming an alias of a refused command'
			. (count($forwarded) ? ', forwarded: ' . implode('; ', $forwarded) : ''));
	}

	/**
	 * And as a command parameter of a multicall, which is where a command
	 * reaches rtorrent without being the call's own method name.
	 */
	public function testAMulticallCannotCarryAnAliasOfARefusedCommand()
	{
		$forwarded = array();
		foreach($this->aliases() as $alias)
		{
			if(XMLRPCProxy::refusedCommandName($alias['target']) === null)
				continue;
			$decision = $this->decide($this->call('d.multicall',
				array('main', '', $alias['name'] . '=/bin/id')));
			if($decision['action'] !== 'reject')
				$forwarded[] = $alias['name'] . ' (' . $alias['file'] . ')';
		}
		$this->assertTrue(count($forwarded) === 0,
			'a multicall carrying an alias of a refused command is rejected'
			. (count($forwarded) ? ', forwarded: ' . implode('; ', $forwarded) : ''));
	}

	/**
	 * The command that makes this matter: system.method.insert defines a new
	 * method, and system.method.set_key binds one to a download event, which is
	 * how a command comes to run without ever being called by name.
	 */
	public function testMethodInsertIsRefusedUnderBothSpellings()
	{
		foreach(array('method.insert', 'system.method.insert',
			'method.set_key', 'system.method.set_key') as $name)
		{
			$this->assertTrue(XMLRPCProxy::refusedCommandName($name) !== null,
				$name . ' names a refused command');
			$decision = $this->decide($this->call($name, array('x')));
			$this->assertTrue($decision['action'] === 'reject',
				$name . ' is rejected at the raw door');
		}
	}
}
