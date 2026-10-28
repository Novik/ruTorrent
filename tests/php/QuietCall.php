<?php

/**
 * Runs a call and collects the diagnostics that @ did not silence, which are
 * the ones that would reach a response. A trait, not a base class: the test
 * runner only instantiates classes whose immediate parent is TestCase.
 */
trait QuietCall
{
	// The call's result and the diagnostics it raised.
	private static function quietly($call)
	{
		$errors = array();
		set_error_handler(function($number, $message) use (&$errors) {
			if(error_reporting() & $number)
				$errors[] = $message;
			return true;
		});
		try
		{
			return array($call(), $errors);
		}
		finally
		{
			restore_error_handler();
		}
	}
}
