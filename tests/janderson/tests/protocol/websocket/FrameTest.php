<?php

namespace janderson\tests\websocket;

use janderson\protocol\websocket\Frame;

class FrameTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider unpackFailureProvider
	 */
	public function testUnpackFailure($buf, $buflen)
	{
		$this->assertFalse(Frame::unpack($buf, $buflen));
	}

	public function unpackFailureProvider()
	{
		return array(
			list($buf, $buflen) = array("", 0), /* No data. */
			list($buf, $buflen) = $this->exampleToBinary("0x81 0x05"), /* short frame, header only, not enough data. */
			list($buf, $buflen) = $this->exampleToBinary("0x81 0x85"), /* short masked frame, header only, not enough data. */
			list($buf, $buflen) = $this->exampleToBinary("0x82 0xFE 0x01 0x00"), /* medium masked frame, partial header only. */
			list($buf, $buflen) = $this->exampleToBinary("0x82 0xFE 0x01 0x00 0x01 0x02 0x03 0x03"), /* medium masked frame, header only, not enough data. */
			list($buf, $buflen) = $this->exampleToBinary("0x82 0x7F 0x00 0x00"), /* long masked frame, partial header only */
			list($buf, $buflen) = $this->exampleToBinary("0x82 0x7F 0x00 0x00 0x00 0x00 0x00 0x01 0x00 0x00"), /* long unmasked frame, header only, not enough data */ 
			list($buf, $buflen) = $this->exampleToBinary("0x82 0xFF 0x00 0x00 0x00 0x00 0x00 0x01 0x00 0x00 0x01 0x02 0x03 0x04"), /* long masked frame, header only, not enough data */ 
		);
	}

	public function testDocumentedExamples()
	{
		/* Unmasked "Hello" */
		$ex = "0x81 0x05 0x48 0x65 0x6c 0x6c 0x6f";
		list($buf, $buflen) = $this->exampleToBinary($ex);

		$frame = Frame::unpack($buf, $buflen);
		$this->assertTrue($frame instanceof Frame);
		$this->assertEquals("Hello", $frame->getPayload());
		$this->assertEquals(5, $frame->getLength());
		$this->assertFalse($frame->isMasked());
		$this->assertTrue($frame->isFin());
		$this->assertNull($frame->getMask());
		$this->assertEquals(Frame::OPCODE_TEXT, $frame->getOpcode());

		/* Masked "Hello" */
		$ex = "0x81 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58";
		list($buf, $buflen) = $this->exampleToBinary($ex);
		list($mask, $masklen) = $this->exampleToBinary("0x37 0xfa 0x21 0x3d");
		list($mask) = array_values(unpack("N", $mask));

		$frame = Frame::unpack($buf, $buflen);
		$this->assertTrue($frame instanceof Frame);
		$this->assertEquals("Hello", $frame->getPayload());
		$this->assertEquals(5, $frame->getLength());
		$this->assertTrue($frame->isMasked());
		$this->assertTrue($frame->isFin());
		$this->assertEquals($mask, $frame->getMask());
		$this->assertEquals(Frame::OPCODE_TEXT, $frame->getOpcode());

		/* Fragmented "Hel" "lo" */
		$ex = "0x01 0x03 0x48 0x65 0x6c";
		list($buf, $buflen) = $this->exampleToBinary($ex);
		$frame1 = Frame::unpack($buf, $buflen);
		$ex = "0x80 0x02 0x6c 0x6f";
		list($buf, $buflen) = $this->exampleToBinary($ex);
		$frame2 = Frame::unpack($buf, $buflen);

		$this->assertTrue($frame1 instanceof Frame);
		$this->assertTrue($frame2 instanceof Frame);
		$this->assertEquals("Hel", $frame1->getPayload());
		$this->assertEquals("lo", $frame2->getPayload());
		$this->assertEquals(3, $frame1->getLength());
		$this->assertEquals(2, $frame2->getLength());
		$this->assertFalse($frame1->isMasked());
		$this->assertFalse($frame2->isMasked());
		$this->assertFalse($frame1->isFin());
		$this->assertTrue($frame2->isFin());
		$this->assertEquals(Frame::OPCODE_TEXT, $frame1->getOpcode());
		$this->assertEquals(Frame::OPCODE_CONTINUATION, $frame2->getOpcode());

		/* Unmasked 256 bytes of data */
		$ex = "0x82 0x7E 0x01 0x00";
		list($buf, $buflen) = $this->exampleToBinary($ex);
		$buf .= str_repeat("a", 256);
		$buflen += 256;

		$frame = Frame::unpack($buf, $buflen);
		$this->assertTrue($frame instanceof Frame);
		$this->assertEquals(str_repeat("a", 256), $frame->getPayload());
		$this->assertEquals(256, $frame->getLength());
		$this->assertFalse($frame->isMasked());
		$this->assertTrue($frame->isFin());
		$this->assertEquals(Frame::OPCODE_BINARY, $frame->getOpcode());

		/* Unmasked 65536 bytes of data */
		$ex = "0x82 0x7F 0x00 0x00 0x00 0x00 0x00 0x01 0x00 0x00";
		list($buf, $buflen) = $this->exampleToBinary($ex);
		$buf .= str_repeat("a", 65536);
		$buflen += 65536;

		$frame = Frame::unpack($buf, $buflen);
		$this->assertTrue($frame instanceof Frame);
		$this->assertEquals(str_repeat("a", 65536), $frame->getPayload());
		$this->assertEquals(65536, $frame->getLength());
		$this->assertFalse($frame->isMasked());
		$this->assertTrue($frame->isFin());
		$this->assertEquals(Frame::OPCODE_BINARY, $frame->getOpcode());
	}

	/**
	 * @dataProvider unpackPackProvider
	 */
	public function testUnpackPack($ex, $add, $addlen)
	{
		/* Get the example, and unpack it. */
		list($buf, $buflen) = $this->exampleToBinary($ex);
		if ($add) {
			$buf .= $add;
			$buflen += $addlen;
		}
		$frame = Frame::unpack($buf, $buflen);


		/* Re-pack the frame. */
		list($checkBuf, $checkBufLen) = $frame->pack();

		/* Re-generate the example buffer. */
		list($buf, $buflen) = $this->exampleToBinary($ex);
		if ($add) {
			$buf .= $add;
			$buflen += $addlen;
		}

		/* Compare the example to the re-packed version. */
		$this->assertEquals($buf, $checkBuf, "Re-packed buffer not equal to the example buffer.");
		$this->assertEquals($buflen, $checkBufLen, "Re-packed buffer length not equal to the example buffer length.");
	}

	public function testRandomMaskGeneration()
	{
		$payload = "Hello, world!";
		$frame = new Frame(TRUE, Frame::OPCODE_TEXT, TRUE, strlen($payload), $payload);

		list($packed, $packedlen) = $frame->pack();
		$masked = substr($packed, -strlen($payload));
		Frame::mask($masked, strlen($payload), $frame->getMask());
		$this->assertEquals($payload, $masked);
	}

	public function unpackPackProvider()
	{
		return array(
			array("0x81 0x05 0x48 0x65 0x6c 0x6c 0x6f", NULL, 0), /* Unmasked Hello */
			array("0x81 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58", NULL, 0), /* Masked Hello */
			array("0x82 0x7E 0x01 0x00", str_repeat("z", 256), 256), /* 256 bytes of unmasked binary data */
			array("0x82 0x7F 0x00 0x00 0x00 0x00 0x00 0x01 0x00 0x00", str_repeat("z", 65536), 65536) /* 65536 bytes of unmasked binary data. */
		);
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
