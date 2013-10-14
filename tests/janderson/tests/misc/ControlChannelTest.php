<?php

namespace janderson\tests\misc;

use janderson\misc\ControlChannel;

class ControlChannelTest extends \PHPUnit_Framework_Testcase
{
	/**
	 * Test that a message can be sent through a control channel.
	 */
	public function testMessage()
	{
		$testStr = "foo bar";
		$ch = new ControlChannel();
		$ch->send($testStr);
		list($str, $fds) = $ch->recv();

		$this->assertEquals($testStr, $str);
		$this->assertEmpty($fds);
	}

	/**
	 * Test that a message and some file descriptors can be sent through a control channel.
	 */
	public function testResource()
	{
		$testStr = 'abc123';
		$ch = new ControlChannel();
		$stream = fopen("/dev/zero", 'r'); // linux/unix specific, but so is the control channel, so...
		$ch->send($testStr, [STDIN, $stream]);
		list($str, $fds) = $ch->recv();

		$this->assertEquals($testStr, $str);
		$this->assertTrue(is_array($fds));
		$this->assertNotEmpty($fds);
		$this->assertTrue(is_resource($fds[0]));
		$this->assertTrue(is_resource($fds[1]));
	}
}
