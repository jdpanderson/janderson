<?php
/**
 * This file defines the Dispatcher interface
 */
namespace janderson\net\http;

/**
 * Dispatcher
 */
class Dispatcher {
	public function __construct() {
		$this->path = realpath(NULL);
	}
	protected $prefixes = array('/' => 'static');

	public function addPrefix() {

	}

	public function dispatch($request) {
		$response = new Response($request);

		/* Static file handler */
		$file = realpath("./{$request->getURI()}");

		if ($file === FALSE || strpos($file, $this->path) !== 0) {
			$response->setStatusCode(HTTP::STATUS_NOT_FOUND);
			$response->setContent("Duh, Not Found");
		} elseif (is_dir($file)) {
			$response->setContent("Directory listing denied");
		} else {
			$response->setContent(file_get_contents($file));
		}
		return $response;
	}
}