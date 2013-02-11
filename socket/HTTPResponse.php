<?php

namespace janderson\net\socket;

use janderson\net\socket\Socket;
use janderson\net\socket\HTTPRequest;

class HTTPResponse {
	const OK = 200;
	const BUF_LEN = 4096;

	const FLAG_CLOSE = 0x01;

	protected $code = self::OK;
	protected $version = '1.0';
	protected $message = "OK";
	protected $request;
	protected $data;
	protected $sent = 0;

	protected $flags;

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

		$this->sent += $socket->send(substr($this->data, $this->sent, self::BUF_LEN), strlen($this->data));

		error_log("Wrote {$this->sent} of " . strlen($this->data) . " bytes");

		if ($this->sent == strlen($this->data)) {
			error_log("Closing");
			$this->flags |= self::FLAG_CLOSE;
			return TRUE;
		}

		return FALSE;
	}

	public function shouldClose() {
		return (bool)($this->flags & self::FLAG_CLOSE);
	}
}
