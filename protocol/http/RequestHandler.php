<?php
/**
 * This file defines the RequestHandler interface
 */
namespace janderson\protocol\http;

/**
 * Any class that expects to handle an HTTP request should implement this interface.
 */
interface RequestHandler {
	/**
	 * Accepts a request object, hands it off for processing.
	 *
	 * @param Request &$request
	 * @param Response &$response
	 */
	public function handle(Request &$request, Response &$response);
}
