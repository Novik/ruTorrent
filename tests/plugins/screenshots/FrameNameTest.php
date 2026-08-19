<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/screenshots/ffmpeg.php');

class FrameNameTest extends TestCase
{
	public function testNamesTheFrameTheNumberAsks()
	{
		$this->assertEquals('/tasks/1/frame0.jpg', ffmpegSettings::frameName('/tasks/1', 0, 0));
		$this->assertEquals('/tasks/1/frame7.png', ffmpegSettings::frameName('/tasks/1', '7', 1));
	}

	public function testAFrameNumberCannotNameAnotherDirectory()
	{
		foreach (array('../../../etc/passwd', '/etc/passwd', '0/../../x', '.') as $no) {
			$name = ffmpegSettings::frameName('/tasks/1', $no, 0);
			$this->assertEquals('/tasks/1/frame0.jpg', $name, 'Refused: '.json_encode($no));
		}
	}
}
