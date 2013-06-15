<?php

namespace janderson\tests\ipc;

use janderson\ipc\SharedMemory;

class SharedMemoryTest extends \PHPUnit_Framework_TestCase
{
	public function testStandardOperation()
	{
		$shm = new SharedMemory();
		$this->assertTrue($shm->write("test"));
		$this->assertEquals("test", $shm->read(4));
		$shm = NULL;
	}

	public function testPrematureDestroy()
	{
		$shm = new SharedMemory();
		$shm->destroy();
		$this->assertFalse($shm->write("foo"));
		$this->assertFalse($shm->read(4));
	}
}