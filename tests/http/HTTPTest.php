<?php

namespace janderson\tests\http;

use janderson\http\HTTP;

class HTTPTest extends \PHPUnit_Framework_TestCase
{
	public function testAccessors()
	{
		/* Check that returned methods are reasonable. */
		$methods = HTTP::getMethods();
		$this->assertTrue(is_array($methods));
		foreach (array('GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE') as $method) {
			$this->assertTrue(in_array($method, $methods));
		}

		/* Check that known versions are returned */
		$versions = HTTP::getVersions();
		$this->assertTrue(is_array($versions));
		$this->assertTrue(in_array("1.0", $versions));
		$this->assertTrue(in_array("1.1", $versions));

		/* Check that common statuses work for getStatusString. */
		foreach (array(200, 404, 500) as $status) {
			$this->assertTrue(strlen(HTTP::getStatusString($status)) > 0);
		}
	}
}