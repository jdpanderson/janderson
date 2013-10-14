<?php

namespace janderson\tests\ipc;

use janderson\ipc\MessageQueue;

/**
 * Provide a little bit more access to the class internals for testing.
 */
class MessageQueueSpy extends MessageQueue
{
	public static $createFail = TRUE;
	protected function create($key)
	{
		return self::$createFail ? FALSE : parent::create($key);
	}
}