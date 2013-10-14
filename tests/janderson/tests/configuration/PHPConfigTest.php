<?php

namespace janderson\tests\configuration;

use janderson\configuration\PHPConfig;

class PHPConfigTest extends \PHPUnit_Framework_TestCase
{
	public function testArrayAccess()
	{
		$cfg = new PHPConfig();
		$this->assertFalse(isset($cfg['foo.bar']));
		$cfg['foo.bar'] = "baz";
		$this->assertTrue(isset($cfg['foo.bar']));
		$this->assertEquals("baz", $cfg->get("foo.bar"));
		$this->assertEquals("baz", $cfg['foo.bar']);
		$this->assertEquals(array("foo" => array("bar" => "baz")), $cfg->get());
		$this->assertEquals(array("bar" => "baz"), $cfg['foo']);
		unset($cfg['foo.bar']);
		$this->assertFalse(isset($cfg['foo.bar']));
	}

	public function testFlatten()
	{
		$cfg = new PHPConfig();
		$cfg['a.b.c'] = 'd';
		$cfg['a.b.c.d'] = 'e';
		$cfg['a.b.c.e'] = 'f';

		$this->assertTrue(is_array($flat = $cfg->flatten()));
		$this->assertEquals('e', $flat['a.b.c.d']);
		$this->assertEquals('f', $flat['a.b.c.e']);
		$this->assertFalse(isset($flat['a.b.c']));
	}

	public function testLoadSave()
	{
		$cfg = new PHPConfig();
		$cfg['foo.bar'] = 123;
		$cfg['foo.baz'] = "teststr";
		$cfg['root'] = "r00t";

		$tmp = tempnam(sys_get_temp_dir(), "cfgtst");

		$cfg->save($tmp);

		$cpy = new PHPConfig();
		$cpy->load($tmp);

		$this->assertEquals($cfg->flatten(), $cpy->flatten());
		$this->assertEquals(123, $cpy['foo.bar']);
		$this->assertEquals("teststr", $cpy['foo.baz']);
		$this->assertEquals("r00t", $cpy['root']);

		$this->assertFalse($cfg->load("/path/to/nonexistant/file.invalid.extension"));
	}
}
