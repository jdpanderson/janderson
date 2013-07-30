<?php
/** 
 * This file defined the HTTPAware interface
 */

namespace janderson\protocol\http;

/**
 * An interface for objects that are aware of HTTP requests and responses.
 */
interface HTTPAware
{
	/**
	 * Tell the HTTPAware interface of the relevant HTTP Request.
	 *
	 * @param Request &$request A reference to the current HTTP Request object.
	 */
	public function setRequest(Request &$request);

	/**
	 * Tell the HTTPAware interface of the relevant HTTP Response.
	 *
	 * @param Response &$response A reference to the current HTTP Response object.
	 */
	public function setResponse(Response &$response);
}