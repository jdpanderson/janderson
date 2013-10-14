<?php

spl_autoload_register(function($class) {
	if (strpos($class, "janderson\\tests") === 0) {
		include __DIR__ . "/" . str_replace("\\", "/", $class) . ".php";
	} else {
		include __DIR__ . "/../src/" . str_replace("\\", "/", $class) . ".php";
	}
});