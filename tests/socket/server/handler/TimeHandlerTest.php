<?php

namespace janderson\tests\socket\server\handler;

use janderson\socket\server\handler\TimeHandler;

class TimeHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function testTimeHandler()
	{
		$buf = "";
		$h = new TimeHandler($buf);
		$unpacked = unpack("N", $buf);
		$time = array_pop($unpacked) - TimeHandler::JAN_1_1970;
		$this->assertTrue($h->read("ign", 3), "Reads should be ignored and should not cause errors.");
		$this->assertTrue(abs(time() - $time) < 1, "time() and returned time are expected to be (nearly) identical");
		$this->assertFalse($h->write(), "TimeHandler is expected to close the connection once data is sent.");
		$h->close();
	}
}
