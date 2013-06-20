<?php

namespace janderson\tests\socket\server\handler;

use janderson\socket\server\handler\EchoHandler;

class EchoHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function testEchoHandler()
	{
		$buf = "";
		$h = new EchoHandler($buf);
		$this->assertTrue($h->read("foo", 3));
		$this->assertEquals("foo", $buf);
		$buf = "";
		$this->assertTrue($h->write());
		$this->assertTrue($h->read("bar", 3));
		$this->assertEquals("bar", $buf);
		$this->assertNull($h->close());
	}
}
