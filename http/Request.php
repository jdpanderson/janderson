<?php

namespace janderson\net\http;

//use janderson\net\http\HTTP;

class Request {
	protected $headers;
	protected $method;
	protected $uri;
	protected $version;
	protected $content;

	public function __construct($method, $uri, $version, $headers = array(), $content = NULL) {
		if (!in_array($method, HTTP::getMethods())) {
			throw new Exception("Invalid method", HTTP::STATUS_BAD_REQUEST);
		}

		$version = explode('/', $version); // HTTP/x.x
		if (count($version) != 2 || $version[0] != 'HTTP' || !in_array($version[1], HTTP::getVersions())) {
			throw new Exception("Unsupported version", HTTP::STATUS_VERSION_NOT_SUPPORTED);
		}

		$this->method = $method;
		$this->uri = $uri;
		$this->version = $version[1];
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

	/**
	 * Get a string representing the HTTP version, typically "1.0" or "1.1", but may rarely be "0.9"
	 *
	 * @return string $version The HTTP version used in the request
	 */
	public function getVersion() {
		return $this->version;
	}

	public function getURI() {
		return $this->uri;
	}
}
