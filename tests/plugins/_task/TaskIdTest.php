<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/_task/task.php');

class TaskIdTest extends TestCase
{
	public function testKeepsAGeneratedId()
	{
		$id = uniqid(time(), true);
		$this->assertEquals($id, rTask::formatId($id));
		$this->assertEquals('17871224646a855320e7f0d1.25181475',
			rTask::formatId('17871224646a855320e7f0d1.25181475'));
	}

	public function testEncodesEverythingElse()
	{
		foreach (array('../../etc', '/etc/passwd', '.', '..', 'a/b', "a\0b") as $value) {
			$formatted = rTask::formatId($value);
			$this->assertEquals(false, strpos($formatted, '/') !== false,
				'No separator survives in '.json_encode($formatted));
			$this->assertEquals(false, ($formatted === '..') || ($formatted === '.'),
				'Neither the tasks directory nor its parent is addressable: '.json_encode($formatted));
		}
	}

	public function testPathOfAnyIdStaysUnderTheTasksDirectory()
	{
		$tasks = FileUtil::getSettingsPath().'/tasks/';
		foreach (array(uniqid(time(), true), '../../etc', '/etc/passwd', '..') as $value) {
			$path = rTask::formatPath($value);
			$this->assertEquals($tasks, substr($path, 0, strlen($tasks)));
			$this->assertEquals(false, strpos(substr($path, strlen($tasks)), '/') !== false,
				'One entry below the tasks directory: '.json_encode($path));
		}
	}
}
