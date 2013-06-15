<?php

/**
 * A simple test assistant that can be run with proc_open, which accepts and runs commands given to it over stdin.
 */
$stdin = fopen('php://stdin', 'r');
while (!feof($stdin)) {
	$line = fgets($stdin);
	eval($line);
}