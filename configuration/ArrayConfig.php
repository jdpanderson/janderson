<?php

namespace janderson\configuration;

/**
 * The nuts and bolts of a simple configuration class.
 */
abstract class ArrayConfig implements Configuration
{
	/**
	 * All stored configuration will be in this array.
	 */
	protected $conf = array();

	/**
	 * Load configuration into the current object.
	 *
	 * @param string $uri Get configuration from this URI or path.
	 * @return bool Returns true if set of configuration directives was loaded, or false on failure.
	 */
	abstract public function load($uri = NULL);

	/**
	 * Save configuration in this object.
	 *
	 * @param string $uri A uniform resource identifier or path to which the configurtion should be saved.
	 * @return bool True if saving was successful, or false otherwise.
	 */
	abstract public function save($uri = NULL);

	/**
	 * Get a configuration directive.
	 *
	 * @param string $directive The configuration directive or parameter.
	 * @param mixed $default The value to be used if the given configuration directive is not available.
	 */
	public function get($directive = NULL, $default = NULL)
	{
		$parts = isset($directive) ? explode('.', $directive) : array();

		$conf = $this->conf;
		foreach ($parts as $part) {
			if (!isset($conf[$part])) {
				return $default;
			}
			$conf = $conf[$part];
		}

		return $conf;
	}

	/**
	 * Set a configuration directive.
	 *
	 * @param string $directive The configuration directive to set.
	 * @param mixed $value The value for the given configuration directive.
	 * @return bool True if setting was successful, or false otherwise.
	 */
	public function set($directive, $value)
	{
		$parts = explode('.', $directive);
		$tail = array_pop($parts);

		$conf = &$this->conf;
		foreach ($parts as $part) {
			if (!isset($conf[$part]) || !is_array($conf[$part])) {
				$conf[$part] = array();
			}
			$conf = &$conf[$part];
		}

		if ($value === NULL) {
			unset($conf[$tail]);
		} else {
			$conf[$tail] = $value;
		}
	}

	/**
	 * Get a flat array with configuration directives.
	 *
	 * @return mixed An array with "." separated keys, e.g. ['foo.bar' => "value"]
	 */
	public function flatten()
	{
		$stack = array();
		$flat = array();
		$conf = $this->conf;

		while (TRUE) {
			$pair = each($conf);
			if ($pair === FALSE) {
				if (empty($stack)) {
					break;
				}
				list($key, $conf) = array_pop($stack);
				continue;
			}

			list($key, $value) = $pair;

			if (is_scalar($value)) {
				$flatkey = array();
				foreach ($stack as $pair) {
					$flatkey[] = reset($pair);
				}
				$flatkey[] = $key;
				$flat[implode(".", $flatkey)] = $value;
			} elseif (is_array($value)) {
				$stack[] = array($key, $conf);
				$conf = $value;
			}
		}
		return $flat;
	}

	
	/**
	 * ArrayAccess interface method wrappers
	 */
	public function offsetExists($offset) { return ($this->get($offset) !== NULL); }
	public function offsetGet($offset) { return $this->get($offset); }
	public function offsetSet($offset, $value) { $this->set($offset, $value); }
	public function offsetUnset($offset) { $this->set($offset, NULL); }
}
