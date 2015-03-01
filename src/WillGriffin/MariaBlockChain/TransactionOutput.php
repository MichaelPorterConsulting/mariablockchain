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

  public $spentat;

  /**
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function __construct($blockchain, $args, $n = false)
  {
    $this->bc = $blockchain;
    $this->trace(__METHOD__);

    if (is_array($args) || $args instanceof \stdClass ) {
      $this->_loadArray($args);
    } else {
      throw new InvalidArgumentException('attempt to load invalid address from array');
    }
  }

  /**
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function stdClass()
  {
    $this->trace(__METHOD__);
    $flds = [
      "txid" => $this->txid,
      "n" => $this->n,
      "value" => $this->value,
      "scriptPubKey" => $this->scriptPubKey,
      "spentat" => $this->spentat
    ];

    if (count($this->addresses)) {
      $flds['addresses'] = array();
      foreach ($this->addresses as $address) {
        $flds['addresses'][] = (string) $address;
      }
    }
    return (object) $flds;
  }

  /**
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  private function _loadArray($arr)
  {
    $this->trace(__METHOD__);

    if (false === $arr instanceof \stdClass) {
      $arr = (object) $arr;
    }

    $flds = ["transaction_id", "vout_id", "txid", "value", "n", "scriptPubKey", "spentat"];
    foreach ($flds as $fld) {
      if (isset($arr->{$fld})) {
        $this->{$fld} = $arr->{$fld};
      }
    }

    $this->value = $this->bc->round($arr->value)

    if (count($arr->scriptPubKey->addresses)) {
      foreach ($arr->scriptPubKey->addresses as $address) {
        $this->addresses[] = $this->bc->addresses->get($address);
      }
    }
  }

}
