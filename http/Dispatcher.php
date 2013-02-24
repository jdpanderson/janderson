<?php
/**
 * This file defines the Dispatcher interface
 */
namespace janderson\net\http;

/**
 * Dispatcher
 */
class Dispatcher {
	public function dispatch($request) {

		new Response($request);
	}

	private function readfile($file) {

	}
}
