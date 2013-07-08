<?php

namespace janderson\tests\protocol\handler;

use janderson\protocol\handler\WebsocketHandler;

class WebsocketHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function testDocumentedUpgrade()
	{
		$request = "GET / HTTP/1.1\nHost: localhost\nConnection: Upgrade\nUpgrade: Websocket\nSec-Websocket-Version: 13\nSec-Websocket-Key: dGhlIHNhbXBsZSBub25jZQ==\n\n";
		$buf = "";
		$h = new WebsocketHandler($buf);
		$this->assertTrue($h->read($request, strlen($request)));
		$this->assertFalse(empty($buf), "Buffer should contain a response");

		/* parse parses and also does basic validation. */
		list($version, $code, $message, $headers, $body) = $this->parseHTTPResponse($buf);

		$this->assertEquals("s3pPLMBiTxaQ9kYGzzhZRbK+xOo=", $headers['sec-websocket-accept'], "Example websocket accept does not match sample value from RFC6455 (page 24)");
		$this->assertEquals("upgrade", strtolower($headers['connection']), "Required 'connection' header missing");
		$this->assertEquals("websocket", strtolower($headers['upgrade']), "Required 'upgrade' header missing");
	}

	public function testUpgradeFail()
	{
		$request = "GET / HTTP/1.1\nHost: localhost\nConnection: Upgrade\nUpgrade: Websocket\nSec-Websocket-Version: 12\nSec-Websocket-Key: dGhlIHNhbXBsZSBub25jZQ==\n\n";
		$buf = "";
		$h = new WebsocketHandler($buf);
		$this->assertTrue($h->read($request, strlen($request)));
		$this->assertFalse(empty($buf), "Buffer should contain a response");

		/* parse parses and also does basic validation. */
		list($version, $code, $message, $headers, $body) = $this->parseHTTPResponse($buf);
		$buf = "";
		$this->assertTrue($h->write()); /* 1.1 - keep alive */
		

		$this->assertEquals(426, $code, "Expecting a version upgrade response code.");

		$request = "GET / HTTP/1.1\nHost: localhost\nConnection: Upgrade\nUpgrade: Websocket\nSec-Websocket-Version: 13\nSec-Websocket-Key: \n\n";
		$this->assertTrue($h->read($request, strlen($request)));

		list($version, $code, $message, $headers, $body) = $this->parseHTTPResponse($buf);
		$buf = "";
		$this->assertTrue($h->write()); /* 1.1 - keep alive */

		$this->assertEquals(400, $code, "Expecting a bad response response if we're missing the key.");

		$request = "GET / HTTP/1.0\n\n";
		$this->assertTrue($h->read($request, strlen($request)));
		$this->assertFalse(empty($buf), "Buffer should contain a response");

		/* parse parses and also does basic validation. */
		list($version, $code, $message, $headers, $body) = $this->parseHTTPResponse($buf);

		$this->assertNotEquals(101, $code, "Should *not* upgrade a normal HTTP request");
		$this->assertTrue(empty($headers['upgrade']));
		$this->assertTrue(empty($headers['sec-websocket-upgrade']));
	}

	protected function parseHTTPResponse($response)
	{
		/* Make sure there's headers and a body separated by standard EOLs */
		$this->assertTrue(strpos($response, "\r\n\r\n") !== FALSE);
		list($rawHeaders, $body) = explode("\r\n\r\n", $response);

		$rawHeaders = explode("\r\n", $rawHeaders);

		/* Make sure the first line is valid, e.g. HTTP/1.0 200 OK */
		$response = explode(" ", array_shift($rawHeaders), 3);
		$this->assertEquals(3, count($response));
		list($version, $code, $message) = $response;

		/* Parse Header: Value pairs */
		$headers = array();
		foreach ($rawHeaders as $rawHeader) {
			list($header, $value) = explode(':', $rawHeader, 2);
			$headers[strtolower(trim($header))] = trim($value);
		}

		$this->assertTrue(in_array($version, array("HTTP/1.0", "HTTP/1.1")), "Only known valid HTTP versions are 1.0 and 1.1");
		$this->assertTrue((int)$code >= 100, "HTTP response code should be 100 or greater");
		$this->assertTrue((int)$code < 600, "HTTP response code should be 599 or less");
		$this->assertFalse(empty($message), "Message associated with response code should not be empty");

		if (isset($headers['content-length'])) {
			$this->assertEquals(strlen($body), $headers['content-length'], "Content length should match body length");
		} else {
			$this->assertTrue(empty($body), "No content length should mean empty body");
		}


		return array($version, $code, $message, $headers, $body);
	}
}
