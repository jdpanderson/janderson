<?php
/**
 * This file defines the Dispatcher interface
 */
namespace janderson\protocol\http;

/**
 * Dispatchable
 */
interface Dispatchable {
	/**
	 * Accepts a request object, hands it off for processing.
	 *
	 * @param Request &$request
	 * @param Response &$response
	 */
	public function dispatch(&$request, &$response);
}
