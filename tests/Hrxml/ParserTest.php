<?php
namespace Hrxml;

use PHPUnit_Framework_TestCase as TestCase;

class UrlTest extends TestCase
{
	public function testSign()
			    {
		$this->assertEquals(
						            'bDv76lTvUdX6vORS96scx7P185c=',
						            Url::sign(
						                'fit-in/560x420/filters:fill(green)/my/big/image.jpg',
						                'MY_SECURE_KEY'
						            )
						        );
	}
	public function testParseFile() {
		$parser = Parser::getInstance();
		$filePath = realpath(__DIR__ . '/sample.xml');
		$candidate = $parser->parseFile($filePath);
		$this->assertEquals('1989', $candidate->birth_year);
	}
	
}
