<?php

namespace janderson\tests\lock;

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
}