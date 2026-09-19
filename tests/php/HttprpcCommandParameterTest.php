<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * The httprpc plugin takes a command name from request input as cmd= and puts
 * it where rtorrent runs it: as a method name of its own in the glbl, ttl and
 * prp modes, and as a command parameter of d.multicall in list, fls, prs, trk
 * and trkall. What it asked of that name was whether the word "execute"
 * appeared anywhere in the string -- not the list the raw-XMLRPC door in the
 * same file refuses over, and not the same question.
 *
 * These pin the two halves of the replacement: that the refused families are
 * refused however they are spelled or nested, and that nothing ruTorrent
 * itself sends as cmd= is refused with them.
 */
class HttprpcCommandParameterTest extends TestCase
{
	// What the substring check answered, kept here as the control: every one
	// of these was admitted, and every one is now refused.
	private function oldFilterWouldAdmit($command)
	{
		return strpos($command, "execute") === false;
	}

	public function testTheSubstringCheckAdmittedTheRefusedFamilies()
	{
		// Not an assertion about the new code: the statement of what was wrong,
		// so that a later change cannot quietly restore it. What the substring
		// check caught, it caught by accident -- a command carrying the word
		// "execute" in an argument was dropped too. What it could not catch is
		// everything that never spells that word.
		foreach(array(
			'import=/etc/passwd',
			'try_import=/etc/passwd',
			'system.shutdown',
			'catch=/bin/id',
			'method.insert=x,simple,"d.name="',
			'method.set_key=event.download.inserted,x,"d.name="',
			'schedule2=x,0,0,"d.name="',
			'log.open_file=x,/tmp/x',
			'network.scgi.open_port=0.0.0.0:5000',
			'session.path.set=/tmp',
			'directory.default.set=/',
			'system.env=PATH',
		) as $command)
			$this->assertTrue($this->oldFilterWouldAdmit($command),
				'the substring check admitted '.$command);
	}

	public function testTheRefusedFamiliesAreRefused()
	{
		foreach(array(
			'execute=/bin/id',
			'execute2=/bin/id',
			'execute.capture=/bin/id',
			'execute.raw.bg=/bin/id',
			'import=/etc/passwd',
			'try_import=/etc/passwd',
			'method.insert=x,simple,"d.name="',
			'method.set_key=event.download.inserted,x,"d.name="',
			'schedule=x,0,0,"d.name="',
			'schedule2=x,0,0,"d.name="',
			'schedule.remove=x',
			'catch=/bin/id',
			'log.execute=/tmp/x',
			'log.open_file=x,/tmp/x',
			'network.scgi.open_port=0.0.0.0:5000',
			'session.path.set=/tmp',
			'directory.default.set=/',
			'system.env=PATH',
			'system.shutdown',
			'system.shutdown.quick',
		) as $command)
			$this->assertTrue(XMLRPCProxy::refusedCommandName($command) !== null,
				$command.' is refused');
	}

	public function testARefusalNamesTheCommandItRefused()
	{
		$this->assertTrue(XMLRPCProxy::refusedCommandName('import=/etc/passwd') === 'import',
			'the refusal of import=/etc/passwd names import');
		$this->assertTrue(XMLRPCProxy::refusedCommandName('d.multicall=main,execute=/bin/id') === 'execute',
			'the refusal of a nested execute names execute, not d.multicall');
	}

	public function testNestedAndDollarIntroducedNamesAreReached()
	{
		foreach(array(
			'$execute=/bin/id',
			'cat=$execute=/bin/id',
			'd.multicall=main,execute=/bin/id',
			'cat="$d.multicall=main,import=/etc/passwd"',
			'branch=1,"execute=/bin/id","d.name="',
			'cat={$system.shutdown}',
		) as $command)
			$this->assertTrue(XMLRPCProxy::refusedCommandName($command) !== null,
				$command.' is refused wherever the name stands');
	}

	public function testWhatRuTorrentItselfSendsIsNotRefused()
	{
		// Every literal ruTorrent registers through theRequestManager.addRequest,
		// in both the spellings theRequestManager.map() produces. A door that
		// refused one of these would cost a column and look like a plugin fault.
		foreach(array(
			'f.prioritize_first=',
			'f.prioritize_last=',
			'd.get_custom=sch_ignore',
			'd.custom=sch_ignore',
			'd.get_custom=chk-state',
			'd.get_custom=chk-time',
			'd.get_custom=seedingtime',
			'd.custom=seedingtime',
			'd.get_custom=addtime',
			'd.get_custom=x-pushbullet',
			'd.get_throttle_name=',
			'd.throttle_name=',
			'cat=$d.views=',
			'cat=$d.get_views=',
			'cat="$t.multicall=d.get_hash=,t.get_scrape_complete=,cat={#}"',
			'cat="$t.multicall=d.get_hash=,t.get_scrape_incomplete=,cat={#}"',
			'd.get_hash=',
			'd.get_name=',
			'd.is_multi_file=',
			't.get_url=',
			'p.get_address=',
		) as $command)
			$this->assertTrue(XMLRPCProxy::refusedCommandName($command) === null,
				$command.' is not refused');
	}

	public function testAnArgumentIsNotMistakenForACommand()
	{
		// A value that begins with a refused word stands where an argument
		// stands, not where a command stands.
		foreach(array(
			'd.get_custom=scheduled',
			'd.get_custom=imported',
			'd.get_custom=logs',
			'd.get_custom=execution',
		) as $command)
			$this->assertTrue(XMLRPCProxy::refusedCommandName($command) === null,
				$command.' is read as an argument, not as a command');
	}

	public function testTheTwoChecksOnACommandNameAreIndependent()
	{
		// php/xmlrpc.php refuses a name that is not shaped like an rtorrent
		// command name; this refuses a name the policy does not allow. Neither
		// answers for the other: "import" is well shaped and refused, and
		// "d.name=" is allowed and not a name at all.
		$this->assertTrue(rXMLRPCCommand::isValidCommandName('import'),
			'import is a well-formed command name');
		$this->assertTrue(XMLRPCProxy::refusedCommandName('import') !== null,
			'and the policy refuses it anyway');
		$this->assertTrue(!rXMLRPCCommand::isValidCommandName('d.multicall=main,execute=x'),
			'a command string is not a well-formed command name');
		$this->assertTrue(XMLRPCProxy::refusedCommandName('d.multicall=main,execute=x') !== null,
			'and the policy refuses what it carries');
	}
}
