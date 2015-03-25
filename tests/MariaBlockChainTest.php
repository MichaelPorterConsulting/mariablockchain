<?php
require_once(dirname(dirname(__FILE__)) . '/src/willgriffin/MariaBlockChain/MariaBlockChain.php');
use willgriffin\MariaBlockChain\MariaBlockChain as MariaBlockChain;

class MariaBlockChainTest extends PHPUnit_Framework_TestCase
{
	public function testCanBeNegated () {


		$this->assertEquals(true, true); //no parse errors at least :p
	}

}
?>
