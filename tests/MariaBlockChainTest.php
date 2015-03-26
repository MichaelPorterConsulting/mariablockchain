<?php
require_once(dirname(dirname(__FILE__)) . '/src/willgriffin/MariaBlockChain/MariaBlockChain.php');
use willgriffin\MariaBlockChain\MariaBlockChain as MariaBlockChain;

class MariaBlockChainTest extends PHPUnit_Framework_TestCase
{
	//todo: moar
	public function testParses () {
		$this->assertEquals(true, true);
	}

}
