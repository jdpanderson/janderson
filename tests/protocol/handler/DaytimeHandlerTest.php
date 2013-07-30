<?php

namespace janderson\tests\protocol\handler;

use janderson\protocol\handler\DaytimeHandler;

class DaytimeHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function testDaytimeHandler()
	{
		$buf = "";
		$buflen = 0;
		$h = new DaytimeHandler($buf, $buflen);
		/* This should work so long as the daytime handler outputs its date in the same timezone as we're reading... Which it does at the moment. */
		$time = strptime(trim($buf), "%A, %B %e, %Y %k:%M:%S-%Z");
		$time = mktime($time['tm_hour'], $time['tm_min'], $time['tm_sec'], $time['tm_mon'] + 1, $time['tm_mday'], $time['tm_year'] + 1900);

		$this->assertTrue($h->read("ign", 3), "Reads should be ignored and should not cause errors.");
		$this->assertTrue(abs(time() - $time) < 1, "time() and returned time are expected to be (nearly) identical");
		$this->assertFalse($h->write(), "DaytimeHandler is expected to close the connection once data is sent.");
		$h->close();
	}
}
