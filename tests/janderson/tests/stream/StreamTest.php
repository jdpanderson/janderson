<?php

namespace janderson\tests\stream;

use janderson\stream\Stream;
use janderson\stream\StreamException;

class StreamTest extends \PHPUnit_Framework_TestCase
{
	public function testInit()
	{
		$stream = new Stream("/dev/zero");
		$this->assertTrue(is_resource($stream->getResource()));
		$stream->close();
		$this->assertFalse(is_resource($stream->getResource()));

		$this->setExpectedException('\\janderson\\stream\\StreamException');
		$stream = new Stream("nonexistant/file/" . uniqid());
	}

	public function testServerFactory()
	{
		$stream = Stream::server("tcp://0.0.0.0:68080");
		$this->assertTrue(is_resource($stream->getResource()));
		$stream->close();
		$this->assertFalse(is_resource($stream->getResource()));

		$this->setExpectedException('\\janderson\\stream\\StreamException');
		Stream::server("invalid://0.0.0.0:1234567"); // invalid
	}
}