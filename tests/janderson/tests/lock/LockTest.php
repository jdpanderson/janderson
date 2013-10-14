<?php

namespace janderson\tests\lock;

use janderson\lock\APCLock;
use janderson\lock\IPCLock;

abstract class LockTest extends \PHPUnit_Framework_TestCase
{
	protected static $impl = "janderson\lock\ExampleLock";
	protected $lock;

	public function setUp()
	{
		$this->lock = new static::$impl();
	}

	public function tearDown()
	{
		if ($this->lock) {
			$this->lock->destroy();
		}
	}

	public function testBasicOperation()
	{
		/* Verify a new lock is not locked off the bat. */
		$this->assertEquals(FALSE, $this->lock->isLocked());

		/* Lock, check, unlock. */
		$this->lock->lock();
		$this->assertEquals(TRUE, $this->lock->isLocked());
		$this->assertEquals(TRUE, $this->lock->unlock());
		$this->assertEquals(FALSE, $this->lock->isLocked());

		/* Trylock, check, trylock, check, unlock. */
		$this->assertEquals(TRUE, $this->lock->trylock());
		$this->assertEquals(TRUE, $this->lock->isLocked());
		$this->assertEquals(FALSE, $this->lock->trylock());
		$this->assertEquals(TRUE, $this->lock->isLocked());
		$this->assertEquals(TRUE, $this->lock->unlock());
		$this->assertEquals(FALSE, $this->lock->isLocked());

		/* Unlocking an unlocked lock should work. */
		$this->assertEquals(TRUE, $this->lock->unlock());
	}


}
