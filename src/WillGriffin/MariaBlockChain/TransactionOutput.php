<?php

namespace WillGriffin\MariaBlockChain;

require_once "Transaction.php";

class TransactionOutput extends Object {

  public $vout_id;
  public $transaction_id;

  public $addresses;

  public $scriptPubKey;

  public $value;
  public $n;
  public $asm;
  public $hex;
  public $reqSigs;
  public $type;

  public $txid;

  public function __construct($blockchain, $args, $n = false)
  {
    $this->blockchain = $blockchain;
    if (is_array($args) || $args instanceof \stdClass ) {
      $this->_loadArray($args);
    } else {
      throw new InvalidArgumentException('attempt to load invalid address from array');
    }
  }


  public function stdClass()
  {
    $flds = [
      "txid" => $this->txid,
      "n" => $this->n,
      "value" => $this->value,
      "scriptPubKey" => $this->scriptPubKey
    ];

    if (count($this->addresses)) {
      $flds['addresses'] = array();
      foreach ($this->addresses as $address) {
        $flds['addresses'][] = (string) $address;
      }
    }
    return (object) $flds;
  }

  private function _loadArray($arr)
  {

    if (false === $arr instanceof \stdClass) {
      $arr = (object) $arr;
    }

    $flds = ["transaction_id", "vout_id", "txid", "value", "n", "scriptPubKey"];
    foreach ($flds as $fld) {
      $this->{$fld} = $arr->{$fld};
    }

    if (count($arr->scriptPubKey->addresses)) {
      foreach ($arr->scriptPubKey->addresses as $address) {
        $this->addresses[] = $this->blockchain->addresses->get($address);
      }
    }
  }

}