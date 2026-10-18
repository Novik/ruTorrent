<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/create/externals.php');

/**
 * $useExternal names the wrapper script in plugins/create and, for every
 * backend but one, the program that wrapper runs. Transmission ships the
 * program as transmission-create, so for that backend the two differ and the
 * key cannot be handed to findEXE() or to the wrapper as a program name.
 */
class CreateExternalEXETest extends TestCase
{
	private function pluginDir()
	{
		return dirname(dirname(dirname(__DIR__))) . '/plugins/create';
	}

	public function testTheTransmissionBackendResolvesToTheProgramTransmissionShips()
	{
		$this->assertEquals('transmission-create', createExternalEXE('transmissioncreate'),
			'the program is transmission-create, not the backend key');
	}

	public function testABackendSpelledLikeItsProgramIsLeftAlone()
	{
		foreach (array('mktorrent', 'buildtorrent', 'createtorrent', 'torrenttools') as $backend)
			$this->assertEquals($backend, createExternalEXE($backend),
				$backend . ' is spelled the same either way');
	}

	public function testTheInternalRealizationIsNotAProgramName()
	{
		$this->assertEquals(false, createExternalEXE(false),
			'false stays false, so action.php still falls through to inner.sh');
	}

	/**
	 * conf.php is where an operator reads which program a backend needs, so a
	 * choice it documents must dispatch to a wrapper that exists and must name
	 * the program the backend resolves to.
	 */
	public function testEveryDocumentedBackendHasAWrapperAndNamesItsProgram()
	{
		$dir = $this->pluginDir();
		$conf = file_get_contents($dir . '/conf.php');
		$count = preg_match_all('/"([a-z]+)" - use program ([a-z-]+)/', $conf, $choices, PREG_SET_ORDER);
		$this->assertTrue($count >= 5, 'conf.php documents the external backends');
		foreach ($choices as $choice)
		{
			$this->assertEquals($choice[2], createExternalEXE($choice[1]),
				$choice[1] . ' resolves to the program conf.php names');
			$this->assertTrue(is_file($dir . '/' . $choice[1] . '.sh'),
				$choice[1] . ' has a wrapper script to dispatch to');
		}
	}
}
