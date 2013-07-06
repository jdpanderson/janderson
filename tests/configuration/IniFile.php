<?php

namespace janderson\tests\configuration;

use janderson\configuration\IniFile;

class IniFileTest extends \PHPUnit_Framework_TestCase
{
	public function testLoadSave()
	{
		$cfg = new IniFile();
		$cfg['foo.bar'] = 123;
		$cfg['foo.baz'] = "teststr";
		$cfg['root'] = "r00t";

		$tmp = tempnam(sys_get_temp_dir(), "cfgtst");

		$cfg->save($tmp);

		$cpy = new IniFile();
		$cpy->load($tmp);

		$this->assertEquals($cfg->flatten(), $cpy->flatten());
		$this->assertEquals(123, $cpy['foo.bar']);
		$this->assertEquals("teststr", $cpy['foo.baz']);
		$this->assertEquals("r00t", $cpy['root']);

		$this->assertFalse($cfg->load("/path/to/nonexistant/file.invalid.extension"));
	}
}
