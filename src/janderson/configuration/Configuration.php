<?php

namespace janderson\configuration;

/**
 * An abstraction to configuration.
 *
 * In order to be compatible across underlying mechanisms, configuration directives are expected to follow these rules:
 *  - Identifiers are alphanumeric only.
 *  - Levels of depth are represented by "." in the directive.
 *
 * For example, configuration directive foo.bar may be represented as follows:
 *  - ini file:
 *    [foo]
 *    bar = "baz"
 *
 *  - xml file:
 *    <foo><bar>baz</bar></foo>
 *    or maybe (but probably not)
 *    <foo bar="baz"></foo>
 *
 *  - JSON file (a php config file would be similar):
 *    {'foo': { 'bar': 'baz' }}
 *
 *  - environment variable:
 *    export foo_bar="baz"
 *
 *  - command line:
 *    --foo-bar="baz"
 *
 * A configuration object may return a structure, so it would be valid to say:
 * <code>
 * $conf->get('foo'); // returns ['bar' => 'baz']
 * // or
 * $conf->get('foo.bar'); // returns "baz"
 * </code>
 */
interface Configuration extends \ArrayAccess
{
	/**
	 * Load configuration into the current object.
	 *
	 * @param string $uri Get configuration from some kind of uniform resource identifier or path.
	 * @return Configuration A loaded set of configuration directives, or false on failure.
	 */
	public function load($uri = NULL);

	/**
	 * Save configuration in this object.
	 *
	 * @param string $uri A uniform resource identifier or path to which the configurtion should be saved.
	 * @return bool True if saving was successful, or false otherwise.
	 */
	public function save($uri = NULL);

	/**
	 * Get a configuration directive.
	 *
	 * @param string $directive The configuration directive or parameter. A configuration type may opt to dump its entire structure if the directive is not set.
	 * @param mixed $default The value to be used if the given configuration directive is not available.
	 */
	public function get($directive = NULL, $default = NULL);

	/**
	 * Set a configuration directive.
	 *
	 * @param string $directive The configuration directive to set.
	 * @param mixed $value The value for the given configuration directive.
	 * @return bool True if setting was successful, or false otherwise.
	 */
	public function set($directive, $value);
}
