<?php

namespace janderson\net\socket;

class HTTPRequest {
	protected $headers;
	protected $method;
	protected $uri;
	protected $version;
	protected $content;

	public function __construct($method, $uri, $version, $headers = array(), $content = NULL) {
		$this->method = $method;
		$this->uri = $uri;
		$this->version = $version;
		$this->headers = $headers;
		$this->content = $content;
	}

	public function setHeaders($headers = array()) {
		$this->headers = $headers;
	}

	/**
	 *
	 * @param $header
	 */
	public function getHeader($header) {
		$header = strtolower($header);

		return isset($this->headers[$header]) ? $this->headers[$header] : FALSE;
	}

	/**
	 * Get the content length, or 0 if there is no content.
	 *
	 * @return int
	 */
	public function getContentLength() {
		return isset($this->headers['content-length']) ? (int)$this->headers['content-length'] : 0;
	}
}
