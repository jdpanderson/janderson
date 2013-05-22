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
		$this->lock->destroy();
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

		/* Unlocking an unlocked lock shouldn't work. */
		$this->assertEquals(FALSE, $this->lock->unlock());
	}

	/**
	 * The example lock is kind enough to warn us if we're going to deadlock a single process.
	 */
	public function testExampleDeadlockException()
	{
		if ($this->lock instanceof \janderson\lock\ExampleLock) {
			$this->setExpectedException('janderson\lock\LockException');
			$this->lock->lock();
			$this->lock->lock();
		} else {
			$this->assertFalse($this->lock instanceof janderson\lock\ExampleLock);
		}
	}
}