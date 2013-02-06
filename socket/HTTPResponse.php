<?php

namespace janderson\net\socket;

use janderson\net\socket\Socket;
use janderson\net\socket\HTTPRequest;

class HTTPResponse {
	const OK = 200;

	const FLAG_CLOSE = 0x01;

	protected $code = self::OK;
	protected $version = '1.0';
	protected $message = "OK";
	protected $request;
	protected $data;

	protected $flags = self::FLAG_CLOSE;

	public function __construct(HTTPRequest $request) {
		$this->request = $request;
	}

	protected function build() {
		$this->data = "HTTP/1.0 200 OK\r\nContent-Length: 6\r\n\r\nfoobar";
	}

	public function send(Socket $socket) {
		if (empty($this->data)) {
			$this->build();
		}

		$socket->send($this->data, strlen($this->data));

		return TRUE;
	}

	public function shouldClose() {
		return (bool)($this->flags & self::FLAG_CLOSE);
	}
}
