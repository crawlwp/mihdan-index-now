<?php
use PHPUnit\Framework\TestCase;

final class SampleWithWPMockTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}
	public function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}
	public function test_sample() {
		$this->assertTrue( true );
	}
}
