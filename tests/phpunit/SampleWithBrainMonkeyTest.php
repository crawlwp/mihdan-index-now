<?php
use PHPUnit\Framework\TestCase;

final class SampleWithBrainMonkeyTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		Brain\Monkey\setUp();
	}

	public function tearDown(): void {
		Brain\Monkey\tearDown();
		parent::tearDown();
	}
	public function test_sample() {
		$this->assertTrue( true );
	}
}
