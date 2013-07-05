<?php

namespace janderson\configuration;

/**
 * The nuts and bolts of a simple configuration class.
 */
class Argv extends ArrayConfiguration
{
	public function __construct($map = array())
	{
		$this->map = is_array($map) ? $map : array();
	}

	/**
	 * Load configuration into the current object.
	 *
	 * @param string $uri Get configuration from a file containing and returning an array.
	 * @return bool Returns true if set of configuration directives was loaded, or false on failure.
	 */
	public function load($uri = NULL)
	{
		$argv = $GLOBALS['argv'];
		array_shift($argv); /* Drop self for now. */
		$argc = count($argv);
		$values = array();
		$conf = array();

		for ($i = 0; $i < $argc; $i++) {
			$option = $argv[$i];
		
			if ($option == "--") {
				/* The rest are "non-option" options. */
				$values = array_merge($values, array_slice($argv, $i + 1));
				break;
			} elseif (strpos($option, '-') === 0) {
				if (strpos($option, '--') === 0) {
					/* This is a --option. */
					$option = ltrim($option, '-');
				} else {
					/* This is a -o option */
					$option = ltrim($option, '-');

					/* If we get -ovalue, transform it into -o=value so it can be parsed below. */
					if (strlen($option) > 1) {
						$option = $option[0] . "=" . substr($option, 1);
					}

					/* Map the short option to a long option if a mapping exists. */
					if (isset($this->map[$option[0]])) {
						$option = $this->map[$option[0]] . substr($option, 1);
					}
				}

				if (strpos($option, "=") !== FALSE) {
					list($option, $value) = explode("=", $option, 2);
				} else {
					if (isset($argv[$i + 1]) && $argv[$i + 1][0] != '-') {
						$value = $argv[++$i];
					} else {
						$value = TRUE;
					}
				}

				$this->setOpt($conf, $option, $value);
			} else {
				/* Non-option option */
				$values[] = $option;
			}
		}

		foreach ($conf as $key => $value) {
			$key = str_replace("-", ".", $key);
			$this->set($key, $value);
		}

		foreach ($values as $value) {
			$this->conf[] = $value;
		}

		return TRUE;
	}

	private function setOpt(&$conf, $option, $value) {
		if (isset($conf[$option])) {
			if (!is_array($conf[$option])) {
				$conf[$option] = array($conf[$option]);
			}
			$conf[$option][] = $value;
		} else {
			$conf[$option] = $value;
		}
	}

	/**
	 * Save configuration in this object. Always fails, because writing a command-line isn't possible.
	 *
	 * @param string $uri A uniform resource identifier or path to which the configurtion should be saved.
	 * @return bool True if saving was successful, or false otherwise.
	 */
	public function save($uri = NULL)
	{
		return FALSE;
	}
}
