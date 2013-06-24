<?php

namespace janderson\tests\websocket;

use janderson\websocket\Frame;

class FrameTest extends \PHPUnit_Framework_TestCase
{
	public function testDocumentedExamples()
	{
		/* Unmasked "Hello" */
		$ex = "0x81 0x05 0x48 0x65 0x6c 0x6c 0x6f";
		list($buf, $buflen) = $this->exampleToBinary($ex);

		var_dump(Frame::unpack($buf, $buflen));

		/* Masked "Hello" */
		$ex = "0x81 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58";
		list($buf, $buflen) = $this->exampleToBinary($ex);

		var_dump(Frame::unpack($buf, $buflen));

		/* Unmasked 256 bytes of data */
		$ex = "0x82 0x7E 0x01 0x00";
		list($buf, $buflen) = $this->exampleToBinary($ex);
		$buf .= str_repeat("a", 256);
		$buflen += 256;

		var_dump(Frame::unpack($buf, $buflen));
	}

	/**
	 * The websocket RFC provides several examples listed as space-separated hex. Decode copy+pastes of that format into binary.
	 */
	protected function exampleToBinary($example)
	{
		$out = "";
		foreach (explode(" ", $example) as $hex) {
			$out .= chr(hexdec($hex));
		}
		return array($out, strlen($out));
	}
}
