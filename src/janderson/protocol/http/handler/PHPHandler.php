<?php
/**
 * This file defines the PHPHandler class
 */
namespace janderson\protocol\http\handler;

/**
 * PHPHandler is a request handler for static content and PHP files, i.e. serves files from a directory tree in the filesystem, and executes PHP.
 */
class PHPHandler extends StaticFileHandler {
	protected function getContent($file)
	{
		if (substr($file, -4) == '.php') {
			ob_start();
			include $file;
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		} else {
			return file_get_contents($file);
		}
	}
}
