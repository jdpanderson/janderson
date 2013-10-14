<?php

namespace janderson\tests\protocol\handler;

use janderson\protocol\handler\EchoHandler;

class EchoHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function testEchoHandler()
	{
		$buf = "";
		$buflen = 0;
		$h = new EchoHandler($buf, $buflen);
		$this->assertTrue($h->read("foo", 3));
		$this->assertEquals("foo", $buf);
		$buf = "";
		$this->assertTrue($h->write());
		$this->assertTrue($h->read("bar", 3));
		$this->assertEquals("bar", $buf);
		$this->assertNull($h->close());
	}
}
