<?php

namespace janderson\tests\protocol\handler;

use janderson\protocol\handler\HTTPHandler;

class HTTPHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function testSimpleRequest()
	{
		$request = "GET / HTTP/1.0\n\n";
		$buf = "";
		$h = new HTTPHandler($buf);
		$this->assertTrue($h->read($request, strlen($request)));
		$this->assertFalse(empty($buf), "Buffer should contain a response");
	
		/* parse parses and also does basic validation. */
		list($version, $code, $message, $headers, $body) = $this->parseHTTPResponse($buf);

		$this->assertFalse($h->write(), "HTTP/1,0 does not support keepalives. False (close socket) expected.");
		$h->close();

		/* After the request is done, both of these should become invalid. */
		$this->assertNull($h->getRequest());
		$this->assertNull($h->getResponse());
	}

	public function testMultiPieceRequest()
	{
		$buf = "";
		$h = new HTTPHandler($buf);
		foreach (array("POST / HTTP/1.0\r\n", "Content-Length: 4\r\n\r\n", "data") as $part) {
			$this->assertTrue($h->read($part, strlen($part)));
		}

		/* parse parses and also does basic validation. */
		list($version, $code, $message, $headers, $body) = $this->parseHTTPResponse($buf);

		$this->assertFalse($h->write(), "HTTP/1,0 does not support keepalives. False (close socket) expected.");
		$h->close();
	}

	/**
	 * @dataProvider invalidRequestProvider
	 * @expectedException janderson\protocol\http\HTTPException
	 */
	public function testInvalidRequests($request)
	{
		$buf = "";
		$h = new HTTPHandler($buf);
		$h->read($request, strlen($request));
	}

	public function invalidRequestProvider()
	{
		return array(
			array("\r\n\r\n"), /* No headers */
			array("GET /\r\n\r\n"), /* Invalid request line */
			array("GET / HTTP/1.0\r\nInvalid\r\n\r\n") /* Invalid header */
		);
	}

	private function parseHTTPResponse($response)
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
