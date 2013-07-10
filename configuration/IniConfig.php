<?php

namespace janderson\configuration;

/**
 * Configuration read from an ini file.
 */
class IniConfig extends ArrayConfig
{
	public function __construct($sections = TRUE)
	{
		$this->sections = (bool)$sections;
	}

	/**
	 * Load configuration into the current object.
	 *
	 * @param string $uri Get configuration from an ini file at this location in the filesystem
	 * @return bool Returns true if set of configuration directives was loaded, or false on failure.
	 */
	public function load($uri = NULL)
	{
		$orig = error_reporting(E_ALL ^ E_WARNING);
		$conf = parse_ini_file($uri, $this->sections);
		error_reporting($orig);

		if (!$conf) {
			return FALSE;
		}

		foreach ($conf as $section => $directives) {
			if (is_scalar($directives)) {
				$this->set($section, $directives);
				continue;
			}

			foreach ($directives as $directive => $value) {
				$this->set("{$section}.{$directive}" , $value);
			}
		}

		return TRUE;
	}

	/**
	 * Save configuration in this object. Always fails, because writing a command-line isn't possible.
	 *
	 * @param string $uri A uniform resource identifier or path to which the configurtion should be saved.
	 * @return bool True if saving was successful, or false otherwise.
	 */
	public function save($uri = NULL)
	{
		$ini = array();
		foreach ($this->flatten() as $directive => $value) {
			$split = $this->sections ? explode(".", $directive, 2) : $directive;

			if (count($split) == 1) {
				$ini[$directive] = $this->exportValue($value);
			} else {
				list($section, $directive) = $split;
				if (!isset($ini[$section])) {
					$ini[$section] = array();
				}
				$ini[$section][$directive] = $this->exportValue($value);
			}
		}

		$lines = array();
		$nosec = array();
		foreach ($ini as $key => $value) {
			if (!is_array($value)) {
				$nosec[] = "{$key} = {$value}";
				continue;
			}

			$lines[] = "";
			$lines[] = "[{$key}]";
			foreach ($value as $directive => $v) {
				$lines[] = "{$directive} = {$v}";
			}
		}

		$inifile = "; This is an automatically generated configuration file.\n; Editing is not recommended.\n\n" . implode("\n", $nosec) . "\n\n" . implode("\n", $lines);
		return file_put_contents($uri, $inifile);
	}

	private function exportValue($value)
	{
		if (is_int($value) || is_float($value)) {
			return $value;
		} else {
			return '"' . addslashes($value) . '"';
		}
	}
}
