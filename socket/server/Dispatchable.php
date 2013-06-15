<?php
/**
 * This file defines the Dispatcher interface
 */
namespace janderson\socket\server;

/**
 * Dispatchable
 */
interface Dispatchable {
	/**
	 * Accepts a request object, hands it off for processing, and returns the processed response.
	 *
	 * @param mixed $request
	 * @return mixed The response
	 */
	public function dispatch($request);
}
