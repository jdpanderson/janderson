<?php

namespace janderson\net\http;

class Response {
	protected $code = HTTP::STATUS_OK;
	protected $version = HTTP::VERSION_1_0;
	protected $message = "OK";
	protected $request;
	protected $content;
	protected $headers = array();

	public function __construct(Request &$request) {
		$this->request = &$request;
		$this->version = $request->getVersion();
	}

	public function setException(Exception $e) {
		$this->code = $e->getCode();
		$this->message = $e->getMessage();
	}

	public function addContent($content) {
		$this->content .= $content;
	}

	public function setContent($content) {
		$this->content = $content;
	}

	public function addHeader($header, $value) {
		if (isset($this->headers[$header])) {
			if (!is_array($this->headers[$header])) {
				$this->headers[$header] = array($this->headers[$header]);
			}
			$this->headers[$header][] = $value;
		} else {
			$this->headers[$header] = $value;
		}
	}

	public function setHeader($header, $value) {
		$this->headers[$header] = $value;
	}

	public function getBuffer() {
		$headers = array(sprintf(
			"HTTP/%s %d %s",
			$this->version,
			$this->code,
			$this->message
		));

		if ($contentLength = strlen($this->content)) {
			$this->headers['Content-Length'] = $contentLength;
		}

		foreach ($this->headers as $header => $value) {
			if (is_array($value)) {
				foreach ($value as $v) {
					$headers[] = "$header: $v";
				}
			} else {
				$headers[] = "$header: $value";
			}
		}
		$headers = implode(HTTP::EOL, $headers) . HTTP::EOL . HTTP::EOL;

		return array($headers . $this->content, strlen($headers) + $contentLength);
	}
}
