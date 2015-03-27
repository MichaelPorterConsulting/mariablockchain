<?php
/**
 * Transaction
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Object.php";

/**
 *
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class Transaction extends Object {

  /**
  *
  * @var int $
  * @since 0.1.0
  */
  private $_vars;


  /**
  *
  * @var int $amount amount transferred in transaction
  * @since 0.1.0
  */
  public $amount;

  /**
  *
  * @var int $fee miner fees paid in transaction
  * @since 0.1.0
  */
  public $fee;

  //
  // /**
  // * toast
  // * @var string $account
  // * @since 0.1.0
  // */
  // public $account;
  //
  // /**
  // *
  // * @var int $
  // * @since 0.1.0
  // */
  // public $address;
  //
  // /**
  // *
  // * @var int $
  // * @since 0.1.0
  // */
  // public $category;

  /**
  *
  * @var int $confirmations confirmations
  * @since 0.1.0
  */
  public $confirmations;

  /**
  *
  * @var string $blockhash hash of the accepting block
  * @since 0.1.0
  */
  public $blockhash;

  /**
  *
  * @var int $blocktime time confirming block was found
  * @since 0.1.0
  */
  public $blocktime;

  /**
  *
  * @var string $txid the transaction id
  * @since 0.1.0
  */
  public $txid;

  /**
  *
  * @var int $time time transaction showed up in wallet or blocktime
  * @since 0.1.0
  */
  public $time;
  //
  // /**
  // *
  // * @var int $
  // * @since 0.1.0
  // */
  // public $timereceived;

  /**
  *
  * @var int $inwallet is the transaction in the wallet .. probably toast
  * @since 0.1.0
  */
  public $inwallet;


  /**
  *
  * @var array $vin array of inputs
  * @since 0.1.0
  */
  public $vin;

  /**
  *
  * @var array $vout array of outputs
  * @since 0.1.0
  */
  public $vout;


  /**
  * constructor
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain the blockchain scope
  * @param int|string|array $tx a transaction_id|txid|array to load transaction from
  * @since 0.1.0
  * @return void
  */
  public function __construct($blockchain, $tx)
  {
    $this->bc = $blockchain;
    $this->_vars = [];

    $this->trace(__METHOD__);

    if (is_numeric($tx)) {
      $this->_vars['transaction_id'] = $tx;
    } else if (is_string($tx)) {
      $this->txid = $tx;
      $arr = $this->bc->transactions->getInfo($tx);
      $this->_loadArray( $arr );
    } else if (is_array($tx) || $tx instanceof \stdClass) {
      $this->_loadArray($tx);
    }

  }



  /**
  * lazy loaded variables, probably dumb and *should* by now be completely
  * phased out in favour of TransactionsController methods
  * @name __get
  * @param string $var variable to retrieve
  * @since 0.1.0
  * @return mixed
  */
  public function __get($var)
  {
    $this->trace(__METHOD__);
    switch ($var)
    {
      case 'transaction_id':

        if (!array_key_exists('transaction_id', $this->_vars)) {
          $this->bc->trace('getting transaction id');
          $this->_vars['transaction_id'] = $this->bc->transactions->getId($this->txid);
          $this->bc->trace(json_encode($this->_vars));
        }
        return $this->_vars['transaction_id'];

      break;

      default:
        $this->error("attempt to access illegal property of Transaction $var");
      break;

    }
  }





  /**
  * as a stdClass
  * @name stdClass
  * @since 0.1.0
  * @return stdClass
  */
  public function stdClass()
  {
    $this->trace(__METHOD__);
    if (count($this->vin) > 0) {
      foreach($this->vin as $vin) {
        $vinArr[] = $vin->stdClass();
      }
    } else {
      $vinArr = false;
    }

    if (count($this->vout) > 0) {
      foreach($this->vout as $vout) {
        $voutArr[] = $vout->stdClass();
      }
    } else {
      $voutArr = false;
    }

    $arr = [
      "txid" => $this->txid,
      "amount" => $this->amount,
      "fee" => $this->fee,
      "confirmations" => $this->confirmations,
      "blockhash" => $this->blockhash,
      "blocktime" => $this->blocktime,
      "time" => $this->time,
      "vin" => $vinArr,
      "vout" => $voutArr
    ];

    return (object) $arr;
  }


  /**
  * returns json representation of transaction
  * @name json
  * @since 0.1.0
  * @return string
  */
  public function json()
  {
    $this->trace(__METHOD__);
    return json_encode($this->stdClass());
  }

  /**
  * load from array
  * @name _loadArray
  * @param $arr
  * @since 0.1.0
  * @return void
  */
  private function _loadArray($arr)
  {
    $this->trace(__METHOD__);
    if (false === $arr instanceof \stdClass) {
      $arr = (object) $arr;
    }

    $flds = ["txid", "amount", "confirmations", "blockhash", "blocktime", "time"];
    foreach ($flds as $fld) {
      if (isset($arr->{$fld}))
        $this->{$fld} = $arr->{$fld};
    }

    $vinTotal = 0;
    if (is_array($arr->vin)) {
      foreach ($arr->vin as $vinArr) {
        $vin = new TransactionInput($this->bc, $vinArr);
        $vinTotal += $vin->vout->value;
        $this->vin[] = $vin;
      }
    }

    $voutTotal = 0;
    if (is_array($arr->vout)) {
      foreach ($arr->vout as $voutArr) {
        $vout = new TransactionOutput($this->bc, $voutArr);
        $voutTotal += $vout->value;
        $this->vout[] = $vout;
      }
    }

    $this->amount = $voutTotal;
    $this->fee = $vinTotal - $voutTotal;
  }

}
