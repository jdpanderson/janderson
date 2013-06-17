<?php

namespace janderson\tests\ipc;

use janderson\ipc\Semaphore;

/**
 * Provide a little bit more access to the class internals for testing.
 */
class SemaphoreSpy extends Semaphore
{
	public static $getFail = TRUE;
	protected function get($key)
	{
		return self::$getFail ? FALSE : parent::get($key);
	}
}