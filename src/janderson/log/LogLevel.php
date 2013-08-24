<?php

namespace janderson\log;

/**
 * Describes log levels
 *
 * This class is nearly identical to the class provided by the PSR-3 LogLevel class:
 * https://github.com/php-fig/log/blob/master/Psr/Log/LogLevel.php
 *
 * The only reason for the existence of this class is to eliminate the need for external dependencies while maintaining compatibility.
 */
class LogLevel
{
	const EMERGENCY = 'emergency';
	const ALERT = 'alert';
	const CRITICAL = 'critical';
	const ERROR = 'error';
	const WARNING = 'warning';
	const NOTICE = 'notice';
	const INFO = 'info';
	const DEBUG = 'debug';
}