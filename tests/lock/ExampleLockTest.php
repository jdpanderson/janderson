<?php

namespace janderson\tests\lock;

use janderson\lock\ExampleLock;

class ExampleLockTest extends LockTest
{
	protected static $impl = "janderson\lock\ExampleLock";

	/**
	 * The example lock is kind enough to warn us if we're going to deadlock a single process.
	 *
	 * @expectedException janderson\lock\LockException
	 */
	public function testExampleDeadlockException()
	{
		$this->assertTrue($this->lock instanceof ExampleLock);
		$this->lock->lock();
		$this->lock->lock();
	}
}