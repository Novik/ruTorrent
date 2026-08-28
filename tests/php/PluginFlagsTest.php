<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/pluginflags.php');

/**
 * getFlag() answers every per-plugin question in conf/plugins.ini, and the two
 * callers read its answer through a (bool) cast:
 *
 *     $userPermissions[$file] = (bool)getFlag($permissions,$file,"enabledByDefault");
 *
 * so what matters is the pair -- what parse_ini_file() makes of the written
 * value, and what the cast then makes of that. "no" reaching the cast as the
 * string 'no' would be true, the opposite of what the admin wrote, so these
 * drive the real parser rather than hand-built arrays.
 */
class PluginFlagsTest extends TestCase
{
	private function parse($ini)
	{
		$file = tempnam(sys_get_temp_dir(), 'plgini');
		file_put_contents($file, $ini);
		$parsed = parse_ini_file($file, true);
		unlink($file);
		return $parsed;
	}

	/**
	 * The compatibility rule: a plugins.ini written before a flag existed must
	 * behave as it did before it existed. Every enabledByDefault upgrade rests
	 * on this, since the shipped plugins.ini only mentions the flag in comments.
	 */
	public function testAFlagNobodySetIsTrue()
	{
		$p = $this->parse("[ratio]\ncanChangeToolbar = no\n");
		$this->assertEquals(true, getFlag($p, 'ratio', 'enabledByDefault'),
			'a flag missing from the plugin section is true');
		$this->assertEquals(true, getFlag($p, 'nosuchplugin', 'enabledByDefault'),
			'a plugin missing from the file entirely is true');
		$this->assertEquals(true, (bool)getFlag($p, 'ratio', 'enabledByDefault'),
			'and it survives the cast the callers apply');
	}

	public function testTheFalseSpellingsAllDisable()
	{
		foreach (array('no', 'off', 'false', '0') as $written) {
			$p = $this->parse("[ratio]\nenabledByDefault = $written\n");
			$this->assertEquals(false, (bool)getFlag($p, 'ratio', 'enabledByDefault'),
				'"'.$written.'" disables the plugin for a user who has not chosen');
		}
	}

	public function testTheTrueSpellingsAllEnable()
	{
		foreach (array('yes', 'on', 'true', '1') as $written) {
			$p = $this->parse("[ratio]\nenabledByDefault = $written\n");
			$this->assertEquals(true, (bool)getFlag($p, 'ratio', 'enabledByDefault'),
				'"'.$written.'" leaves the plugin enabled');
		}
	}

	public function testTheDefaultSectionAnswersForPluginsThatDoNotSayOtherwise()
	{
		$p = $this->parse("[default]\nenabledByDefault = no\n[ratio]\nenabledByDefault = yes\n[rss]\ncanChangeMenu = no\n");
		$this->assertEquals(false, (bool)getFlag($p, 'rss', 'enabledByDefault'),
			'a plugin with no opinion of its own takes the [default] one');
		$this->assertEquals(false, (bool)getFlag($p, 'unlisted', 'enabledByDefault'),
			'so does a plugin with no section at all');
		$this->assertEquals(true, (bool)getFlag($p, 'ratio', 'enabledByDefault'),
			'and a plugin that does have an opinion overrides [default]');
	}
}
