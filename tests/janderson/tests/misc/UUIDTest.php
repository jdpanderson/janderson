<?php

namespace janderson\tests\misc;

use janderson\misc\UUID;

class UUIDTest extends \PHPUnit_Framework_TestCase
{
	public function testV3()
	{
		/* Stolen from http://docs.python.org/library/uuid.html */
		$this->assertEquals('6fa459ea-ee8a-3ca4-894e-db77e160355e', UUID::v3(UUID::NAMESPACE_DNS, 'python.org'));
	}

	public function testV4()
	{
		$this->validate(UUID::v4(), 4);
	}

	public function testV5()
	{
		/* Stolen from http://docs.python.org/library/uuid.html */
		$this->assertEquals('886313e1-3b8a-5372-9b90-0c9aee199e5d', UUID::v5(UUID::NAMESPACE_DNS, 'python.org'));
	}

	private function validate($uuid, $version)
	{
		$uuid = str_replace(array('-', '{', '}'), '', $uuid);

		/* Parse the hex characters */
		$uuid = sscanf($uuid, "%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x");

		$this->assertEquals(16, count($uuid), 'Expected to find 16 hex pairs');
		$this->assertEquals($version, $uuid[6] >> 4, "Version in UUID does not match $version");
		$this->assertEquals(2, $uuid[8] >> 6, 'The two most significant bits of byte 9 must be 1 and 0');
	}
}
