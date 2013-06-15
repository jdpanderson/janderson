<?php

namespace janderson\tests\lock;

use janderson\lock\IPCLock;
use janderson\ipc\Semaphore;
use janderson\ipc\SharedMemory;

class IPCLockTest extends LockTest
{
	protected static $impl = "janderson\lock\IPCLock";

	public function setUp()
	{
		if (!extension_loaded('sysvsem') || !extension_loaded('sysvshm')) {
			$this->markTestSkipped("SysV-IPC modules not available");
		}
		parent::setUp();
	}

	public function testTwoLocks()
	{
		$lock1 = new IPCLock("locktest");
		$lock2 = new IPCLock("locktest");

		$lock1->lock();
		$this->assertFalse($lock2->trylock());
		$this->assertTrue($lock1->unlock());
		$this->assertTrue($lock2->trylock());
		$this->assertFalse($lock1->trylock());
		$this->assertTrue($lock2->unlock());

		$lock1->destroy();
		//$lock2->destroy(); // Only one or the other is needed.
	}

	public function testAlternateConstructor()
	{
		$sem = new Semaphore(__FUNCTION__);
		$shm = new SharedMemory(__FUNCTION__);

		$lock = new IPCLock([$sem, $shm]);
		$lock->lock();
		$this->assertFalse($lock->trylock());
		$this->assertTrue($lock->unlock());
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidArguments()
	{
		$lock = new IPCLock(['a','b']);
	}

	/**
	 * @expectedException \janderson\lock\LockException
	 */
	public function testAcquireFail()
	{
		$sem = $this->getMockBuilder('\janderson\ipc\Semaphore')->disableOriginalConstructor()->getMock();
		$shm = $this->getMockBuilder('\janderson\ipc\SharedMemory')->disableOriginalConstructor()->getMock();
		$shm->expects($this->any())->method('isNew')->will($this->returnValue(TRUE));
		$sem->expects($this->any())->method('acquire')->will($this->returnValue(FALSE));

		$lock = new IPCLock([$sem, $shm]);
	}

	/**
	 * @group debug
	 */
	public function testFlakySemaphore()
	{
		$sem = $this->getMockBuilder('\janderson\ipc\Semaphore')->disableOriginalConstructor()->getMock();
		/* The idea is to fail to get the semaphore once in trylock() then
		 * succeed the second time to test retry logic. That should be at(1),
		 * not at(2). For some reason PHPUnit seems to miscount - tested using
		 * a test spy with debug statements indicates that it does correspond
		 * to the second call.... I'm not wasting any more time on it, so
		 * documenting the wierdness here in case someone ever wants to dig. 
		 */
		//$sem->expects($this->at(1))->method('acquire')->will($this->returnValue(FALSE));
		$sem->expects($this->at(2))->method('acquire')->will($this->returnValue(FALSE));
		$sem->expects($this->at(5))->method('acquire')->will($this->returnValue(FALSE));
		$sem->expects($this->any())->method('acquire')->will($this->returnValue(TRUE));
		$shm = new SharedMemory();

		$lock = new IPCLock([$sem, $shm]);
		$lock->lock(); /* Semaphore should fail once then succeed. That's not visible here, but code coverage should hit the retry line in lock(). */
		$this->assertFalse($lock->trylock());
		$this->assertFalse($lock->unlock());
		$this->assertTrue($lock->unlock());
		$this->assertTrue($lock->trylock());

		$lock->destroy();
	}

	public function testFlakySharedMemory()
	{
		$sem = $this->getMockBuilder('\janderson\ipc\Semaphore')->disableOriginalConstructor()->getMock();
		$shm = $this->getMockBuilder('\janderson\ipc\SharedMemory')->disableOriginalConstructor()->getMock();
		$shm->expects($this->any())->method('isNew')->will($this->returnValue(FALSE));
		$shm->expects($this->any())->method('write')->will($this->returnValue(FALSE)); /* SHM that always fails to write */
		$sem->expects($this->any())->method('acquire')->will($this->returnValue(TRUE));

		$lock = new IPCLock([$sem, $shm]);
		$this->assertFalse($lock->trylock());
	}
}