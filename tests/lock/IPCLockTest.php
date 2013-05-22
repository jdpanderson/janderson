<?php

namespace janderson\tests\lock;

class IPCLockTest extends LockTest
{
	protected static $impl = "janderson\lock\IPCLock";

	public function setUp()
	{
		if (!extension_loaded('sysvsem') || !extension_loaded('sysvshm')) {
			$this->markTestSkipped("APC not available");
		}
		parent::setUp();
	}
}