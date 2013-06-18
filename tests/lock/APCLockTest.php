<?php

namespace janderson\tests\lock;

use \janderson\tests\assistant\TestAssistant;

class APCLockTest extends LockTest
{
	protected static $impl = "janderson\lock\APCLock";

	public function setUp()
	{
		if (!extension_loaded('apc')) {
			$this->markTestSkipped("APC not available");
		}
		parent::setUp();
	}

	public function testTwoLocks()
	{
		$lock1 = new \janderson\lock\APCLock("locktest");
		$lock2 = new \janderson\lock\APCLock("locktest");

		$lock1->lock();
		$this->assertFalse($lock2->trylock());
		$this->assertTrue($lock1->unlock());
		$this->assertTrue($lock2->trylock());
		$this->assertFalse($lock1->trylock());
	}

	/**
	 * @expectedException janderson\lock\LockException
	 */
	public function testInitFail()
	{
		APCLockSpy::$initFail = TRUE;
		$lock = new APCLockSpy();
	}

	/**
	 * @expectedException janderson\lock\LockException
	 */
	public function testStoreFail()
	{
		APCLockSpy::$storeFail = TRUE;
		$lock = new APCLockSpy();
	}

	public function testTrylockFail()
	{
		$lock = new APCLockSpy();
		$expected = $lock->inc();
		$lock->decFail = TRUE;
		$this->assertFalse($lock->trylock()); // The inc should make the lock fail, but it should retry the decrement.
		$this->assertEquals($expected, $lock->get());
		$lock->dec();

		$lock->incFail = TRUE;
		$this->assertFalse($lock->trylock()); // Failure to increment should outright fail.
		$this->assertEquals(0, $lock->get()); // Failure should not have increased the stored value.
		$this->assertTrue($lock->trylock()); // Trying again should work.

		$lock->destroy();
	}

	public function testUnlockFail()
	{
		$lock = new APCLockSpy();
		$this->assertTrue($lock->trylock());
		$lock->decFail = TRUE;
		$this->assertTrue($lock->unlock()); /* If dec fails once, this should retry. */
		$this->assertEquals(0, $lock->get());
		
		$lock->destroy();
	}

	public function testLockFail()
	{
		$lock = new APCLockSpy();
		$lock->incFail = TRUE;
		$lock->lock();
		$this->assertEquals(1, $lock->get()); // Should have retried and succeeded.
		$lock->destroy();
	}
}
