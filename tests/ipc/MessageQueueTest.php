<?php

namespace janderson\tests\ipc;

use janderson\ipc\MessageQueue;

class MessageQueueTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		if (!extension_loaded('sysvmsg')) {
			$this->markTestSkipped("sysvmsg is required, but not loaded");
		}
	}

	public function testQueue()
	{
		$value = "test123";

		$q = new MessageQueue();
		$this->assertTrue($q->send($value));
		$this->assertEquals($value, $q->receive());
		$this->assertFalse($q->receive());

		$this->assertTrue($q->send($value, 123));
		$this->assertFalse($q->receive(122));
		$this->assertEquals($value, $q->receive(123));
		$this->assertFalse($q->receive(123));
		$q->destroy();
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidLength()
	{
		$q = new MessageQueue("test", -1);
	}

	/**
	 * @expectedException janderson\ipc\IPCException
	 */
	public function testCreateFail()
	{
		$q = new MessageQueueSpy("");
	}
}