<?php

namespace janderson\tests\ipc;

use janderson\ipc\Semaphore;

class SemaphoreTest extends \PHPUnit_Framework_TestCase
{
	public function testSemaphore()
	{
		$sem = new Semaphore();
		$this->assertTrue($sem->acquire());
		$this->assertTrue($sem->release());

		/* Don't know how do a lot more without multi-process because Semaphores are blocking :) */
		
		$sem->destroy();
	}

	/**
	 * @expectedException janderson\ipc\IPCException
	 */
	public function testGetFail()
	{
		$q = new SemaphoreSpy("");
	}
}