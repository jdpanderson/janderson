<?php

namespace janderson\net\socket;

use janderson\net\socket\Socket;
use janderson\net\socket\HTTPRequest;

class HTTPResponse {
	const OK = 200;


	protected $code = self::OK;
	protected $version = '1.0';
	protected $message = "OK";
	protected $request;
	protected $data;
	protected $sent = 0;

	protected $flags;

	public function __construct(HTTPRequest &$request) {
		$this->request = &$request;
	}

	public function getBuffer() {
		$buf = "HTTP/1.0 200 OK\r\nContent-Length: 6\r\n\r\nfoobar";
		return array($buf, strlen($buf));
	}
}
