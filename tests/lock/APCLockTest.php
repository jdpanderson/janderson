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
}