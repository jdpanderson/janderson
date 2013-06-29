<?php

namespace janderson\conf;

interface Conf
{
	/**
	 * Get a configuration directive.
	 */
	public function get($directive);

	/**
	 * Set a configuration directive.
	 */
	public function set($directive, $value);
}