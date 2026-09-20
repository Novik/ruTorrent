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
		return array_merge($all, $this->inlineAliases());
	}

	/**
	 * The seventh table. php/settings.php builds one inline for every daemon
	 * above 0.8.6, before it requires any of the files, and a caller reaches
	 * rXMLRPCCommand through that one too. It is read out of the source rather
	 * than written out here, so an entry added to it is covered without this
	 * file being edited — and the count is asserted, so a change in how it is
	 * written is a failure rather than a silent empty table.
	 */
	private function inlineAliases()
	{
		$source = file_get_contents(__DIR__ . '/../../php/settings.php');
		$start = strpos($source, '$this->aliases = array');
		if($start === false)
			return array();
		$end = strpos($source, ');', $start);
		$block = substr($source, $start, ($end === false) ? null : $end - $start);

		$found = array();
		preg_match_all('/"([^"]+)"\s*=>\s*array\(\s*"name"\s*=>\s*"([^"]+)"/',
			$block, $matches, PREG_SET_ORDER);
		foreach($matches as $match)
			$found[] = array(
				'file'   => 'settings.php',
				'name'   => $match[1],
				'target' => $match[2],
			);
		return $found;
	}

	/**
	 * The names the proxy holds to the directory this server allows.
	 */
	private function directoryCommands()
	{
		$property = new ReflectionProperty('XMLRPCProxy', 'directoryCommands');
		$property->setAccessible(true);
		return $property->getValue();
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

	/**
	 * The same door with a boundary that actually excludes something, for the
	 * commands whose refusal is about where they write.
	 */
	private function decideConfined($xml)
	{
		return XMLRPCProxy::decide($xml, 'sanitize',
			array('d.custom1.set', 'd.directory.set', 'd.directory_base.set'), false,
			array('directory' => array('root' => '/torrents1', 'resolve' => null)));
	}

	public function testTheAliasTablesAreReadable()
	{
		$aliases = $this->aliases();
		$this->assertTrue(count($aliases) > 300,
			'the alias tables were loaded (' . count($aliases) . ' entries)');
	}

	/**
	 * All seven of them: the six php/methods-*.php files and the one
	 * php/settings.php builds inline.
	 */
	public function testAllSevenAliasTablesAreRead()
	{
		$files = array();
		foreach($this->aliases() as $alias)
			$files[$alias['file']] = true;
		ksort($files);

		$this->assertTrue(count($files) === 7,
			'all seven alias tables are read (' . implode(', ', array_keys($files)) . ')');
		$this->assertTrue(count($this->inlineAliases()) >= 2,
			'the table php/settings.php builds inline was parsed out of it ('
			. count($this->inlineAliases()) . ' entries)');
	}

	/**
	 * A directory setter is confined by what it does, not by how it is
	 * spelled. d.set_directory is d.directory.set on a daemon that registers
	 * the older name, so a path outside the boundary is refused under both —
	 * at the top level, as a member of a system.multicall, and written as a
	 * command inside either kind of multicall.
	 */
	public function testAnAliasOfADirectorySetterIsConfinedUnderItsOwnName()
	{
		$directoryCommands = $this->directoryCommands();
		$hash = str_repeat('A', 40);
		$outside = '/var/www/html';
		$checked = 0;

		foreach($this->aliases() as $alias)
		{
			if(!in_array($alias['target'], $directoryCommands, true))
				continue;
			$checked++;
			$name = $alias['name'];
			$where = $name . ' (' . $alias['file'] . ')';

			$calls = array(
				'as a call of its own' => $this->call($name, array($hash, $outside)),
				'as a d.multicall command parameter' =>
					$this->call('d.multicall', array('main', '', $name . '=' . $outside)),
				'as a system.multicall member' => $this->systemMulticall($name,
					array($hash, $outside)),
				'inside a system.multicall member' => $this->systemMulticall('d.multicall',
					array('main', '', $name . '=' . $outside)),
			);

			foreach($calls as $shape => $request)
			{
				$decision = $this->decideConfined($request);
				$this->assertTrue($decision['action'] === 'reject',
					$where . ' ' . $shape . ' is refused outside the boundary');
				$this->assertTrue(strpos($decision['payload'], $outside) === false,
					$where . ' ' . $shape . ' puts no outside path on the wire');
			}
		}

		$this->assertTrue($checked > 0,
			'the tables name at least one alias of a directory setter (' . $checked . ')');
	}

	private function systemMulticall($method, $params)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall'
			. '</methodName><params><param><value><array><data>'
			. '<value><struct><member><name>methodName</name><value><string>'
			. htmlspecialchars($method, ENT_NOQUOTES) . '</string></value></member>'
			. '<member><name>params</name><value><array><data>';
		foreach($params as $p)
			$xml .= '<value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
				. '</string></value>';
		return $xml . '</data></array></value></member></struct></value>'
			. '</data></array></value></param></params></methodCall>';
	}

	/**
	 * And the same at every one of those positions for the refused families,
	 * which the tests below already ask about at the first two.
	 */
	public function testASystemMulticallCannotCarryAnAliasOfARefusedCommand()
	{
		$forwarded = array();
		foreach($this->aliases() as $alias)
		{
			if(XMLRPCProxy::refusedCommandName($alias['target']) === null)
				continue;

			$asMember = $this->decide($this->systemMulticall($alias['name'], array('x')));
			if($asMember['action'] !== 'reject')
				$forwarded[] = $alias['name'] . ' as a member (' . $alias['file'] . ')';

			$inMember = $this->decide($this->systemMulticall('d.multicall',
				array('main', '', $alias['name'] . '=/bin/id')));
			if($inMember['action'] !== 'reject')
				$forwarded[] = $alias['name'] . ' inside a member (' . $alias['file'] . ')';
		}
		$this->assertTrue(count($forwarded) === 0,
			'a system.multicall carrying an alias of a refused command is rejected'
			. (count($forwarded) ? ', forwarded: ' . implode('; ', $forwarded) : ''));
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
