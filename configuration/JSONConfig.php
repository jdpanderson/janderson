<?php

namespace janderson\configuration;

/**
 * Configuration read from an ini file.
 */
class JSONConfig extends ArrayConfiguration
{
	/**
	 * Load configuration into the current object.
	 *
	 * @param string $uri Get configuration from an ini file at this location in the filesystem
	 * @return bool Returns true if set of configuration directives was loaded, or false on failure.
	 */
	public function load($uri = NULL)
	{
		$orig = error_reporting(E_ALL ^ E_WARNING);
		$conf = json_decode(file_get_contents($uri), TRUE);
		error_reporting($orig);

		if (!is_array($conf)) {
			return FALSE;
		}

		$this->conf = array_merge_recursive($this->conf, $conf);
	}

	/**
	 * Save configuration in this object. Always fails, because writing a command-line isn't possible.
	 *
	 * @param string $uri A uniform resource identifier or path to which the configurtion should be saved.
	 * @return bool True if saving was successful, or false otherwise.
	 */
	public function save($uri = NULL)
	{
		$orig = error_reporting(E_ALL ^ E_WARNING);
		$result = file_put_contents($uri, json_encode($this->conf));
		error_reporting($orig);

		return $result;
	}
}
