<?php
/**
 * This file defines the StaticDispatcher interface
 */
namespace janderson\protocol\http;

use janderson\protocol\http\Dispatchable;

/**
 * StaticDispatcher is a dispatcher for static content, i.e. serves files from a directory tree in the filesystem.
 */
class PHPDispatcher extends StaticDispatcher {
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
