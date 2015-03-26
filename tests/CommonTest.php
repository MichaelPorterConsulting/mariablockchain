<?php
require_once(dirname(dirname(__FILE__)) . '/src/willgriffin/MariaBlockChain/MariaBlockChain.php');
use willgriffin\MariaBlockChain\Common as Common;

class CommonTest extends PHPUnit_Framework_TestCase
{
	//todo: moar
	public function testUnitConversion()
  {
		$this->assertEquals(Common::toSatoshi(1.61803399), 161803399);
		$this->assertEquals(Common::toSatoshi('1.61803399'), 161803399);
		$this->assertEquals(Common::toBTC(161803399), 1.61803399);
	}

	public function testHooks()
	{
		$counter = 0;
		$obj = new Common();

		$obj->hook('randomevent', function() use (&$counter) {
			$counter++;
		});

		$this->assertEquals($obj->hasHook('randomevent'), true);

		$obj->emit("randomevent");

		$this->assertEquals($counter, 1);
		$obj->emit("randomevent");
		$this->assertEquals($counter, 2);

	}

	public function testPrepDate()
	{
		$common = new Common();
		$timenow = time();
		$prepped = date("Y-m-d H:i:s", $timenow);
		$preppedStart = date("Y-m-d 00:00:00", $timenow);
		$preppedEnd = date("Y-m-d 23:59:59", $timenow);

		$this->assertEquals($prepped, $common->prepDate($timenow));
		$this->assertEquals($preppedStart, $common->prepDate($timenow, 'start'));
		$this->assertEquals($preppedEnd, $common->prepDate($timenow, 'end'));

		$this->assertEquals($prepped, $common->prepDate(date('F jS Y h:i:s A', $timenow)));
		$this->assertEquals($preppedStart, $common->prepDate(date('F jS Y h:i:s A', $timenow), 'start'));
		$this->assertEquals($preppedEnd, $common->prepDate(date('F jS Y h:i:s A', $timenow), 'end'));

	}
}
