<?php

namespace janderson\tests\ipc;

use janderson\ipc\SharedMemory;

class SharedMemoryTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		if (!extension_loaded('shmop')) {
			$this->markTestSkipped("sysvmsg is required, but not loaded");
		}
	}

	public function testStandardOperation()
	{
		$shm = new SharedMemory();
		$this->assertTrue($shm->write("test"));
		$this->assertEquals("test", $shm->read(4));
		$shm->destroy();
	}

	public function testPrematureDestroy()
	{
		$shm = new SharedMemory();
		$shm->destroy();
		$this->assertFalse($shm->write("foo"));
		$this->assertFalse($shm->read(4));
	}

	public function testTwoParallelInstances()
	{
		$shm1 = new SharedMemory("testKey");
		$shm2 = new SharedMemory("testKey");

		$this->assertTrue($shm1->write("test"));
		$this->assertEquals("test", $shm2->read(4));

		$shm1->__destruct();
		unset($shm1);
		$shm2->destroy();
	}

	/**
	 * @expectedException janderson\ipc\IPCException
	 */
	public function testOpenFail()
	{
		$shm = new SharedMemorySpy();
	}
}