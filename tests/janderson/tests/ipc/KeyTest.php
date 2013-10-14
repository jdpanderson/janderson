<?php

namespace janderson\tests\ipc;

use \janderson\ipc\IPCKey;

class KeyTest extends \PHPUnit_Framework_TestCase
{
	public function testGetKey()
	{
		$this->assertEquals(123, IPCKey::create(123), "Int key should return the same int key.");
		$this->assertTrue(is_int(IPCKey::create("123")));
		$this->assertNotEquals(123, IPCKey::create("123"));
		$this->assertTrue(is_int(IPCKey::create(new \Exception())), "An exception should convert to a string, which should allow a key to be generated");
	}
}