<?php

namespace janderson\configuration;

/**
 * The nuts and bolts of a simple configuration class.
 */
class PHPConfiguration extends ArrayConfiguration
{
	/**
	 * Load configuration into the current object.
	 *
	 * @param string $uri Get configuration from a file containing and returning an array.
	 * @return bool Returns true if set of configuration directives was loaded, or false on failure.
	 */
	public function load($uri = NULL)
	{
		$orig = error_reporting(E_ALL ^ E_WARNING);
		$conf = include $uri;
		error_reporting($orig);

		if (!is_array($conf)) {
			return FALSE;
		}

		$this->conf = empty($this->conf) ? $conf : array_merge_recursive($this->conf, $conf);
		return TRUE;
	}

	/**
	 * Save configuration in this object.
	 *
	 * @param string $uri A uniform resource identifier or path to which the configurtion should be saved.
	 * @return bool True if saving was successful, or false otherwise.
	 */
	public function save($uri = NULL)
	{
		$conf = sprintf("<?php return %s;", var_export($this->conf, TRUE));

		return file_put_contents($uri, $conf);
	}
}
