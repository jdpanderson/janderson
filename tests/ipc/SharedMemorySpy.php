<?php

namespace janderson\tests\ipc;

use janderson\ipc\SharedMemory;

/**
 * Provide a little bit more access to the class internals for testing.
 */
class SharedMemorySpy extends SharedMemory
{
	public static $openFail = TRUE;
	protected function open($key, $size)
	{
		return self::$openFail ? FALSE : parent::open($key, $size);
	}
}