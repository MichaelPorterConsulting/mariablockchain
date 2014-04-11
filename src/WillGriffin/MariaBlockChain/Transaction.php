<?php

namespace WillGriffin\MariaBlockChain;

require_once "BlockChain.php";

class Transaction extends Object {

  private $_vars;

  public $amount;
  public $fee;

  public $account;
  public $address;
  public $category;

  public $confirmations;
  public $blockhash;
  public $blockindex;
  public $blocktime;
  public $txid;
  public $time;
  public $timereceived;
  public $inwallet;

  public $vin;
  public $vout;

  public function __construct($blockchain, $tx) {

    $this->blockchain = $blockchain;
    $this->_vars = [];

    if (is_numeric($tx)) {
      $this->_vars['transaction_id'] = $tx;
    } else if (is_string($tx)) {
      $this->txid = $tx;
      $this->_loadArray( $this->blockchain->transactions->getInfo($tx) );
    } else if (is_array($tx) || $tx instanceof \stdClass) {
      $this->_loadArray($tx);
    }

  }

  public function __get($var)
  {

    switch ($var)
    {
      case 'transaction_id':

        if (!array_key_exists('transaction_id', $this->_vars)) {
          $this->blockchain->trace('getting transaction id');
          $this->_vars['transaction_id'] = $this->blockchain->transactions->getID($this->txid);
          $this->blockchain->trace(json_encode($this->_vars));
        }
        return $this->_vars['transaction_id'];

      break;

      default:
        $this->error("attempt to access illegal property of Transaction $var");
      break;

    }

  }

  public function stdClass()
  {

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
      "blockindex" => $this->blockindex,
      "blocktime" => $this->blocktime,
      "time" => $this->time,
      "vin" => $vinArr,
      "vout" => $voutArr
    ];

    return (object) $arr;
  }

  public function json()
  {
    return json_encode($this->stdClass());
  }


  private function _loadArray($arr)
  {
    if (false === $arr instanceof \stdClass) {
      $arr = (object) $arr;
    }

    $flds = ["txid", "amount", "confirmations", "blockhash", "blockindex", "blocktime", "time"];
    foreach ($flds as $fld) {
      $this->{$fld} = $arr->{$fld};
    }

    if (is_array($arr->vin)) {
      foreach ($arr->vin as $vinArr) {
        $vin = new TransactionInput($this->blockchain, $vinArr);
        $vinTotal += $vin->vout->value;
        $this->vin[] = $vin;
      }
    }

    if (is_array($arr->vout)) {
      foreach ($arr->vout as $voutArr) {
        //echo json_encode($vout)."\n";
        $vout = new TransactionOutput($this->blockchain, $voutArr);
        $voutTotal += $vout->value;
        $this->vout[] = $vout;
      }
    }

    $this->amount = $voutTotal;
    $this->fee = $vinTotal - $voutTotal;

  }

}