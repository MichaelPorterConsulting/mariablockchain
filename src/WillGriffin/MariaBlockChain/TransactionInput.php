<?php

namespace WillGriffin\MariaBlockChain;

require_once "Transaction.php";

class TransactionInput  extends Object {

  protected $_vout;

  public $txid;
  public $n;

  public $scriptSig;
  public $sequence;

  public function __construct($blockchain, $args)
  {
    $this->bc = $blockchain;

    if (is_array($args) || ($args instanceof \stdClass)) {
      $this->_loadArray($args);
    } else {
      echo "\nCouldn't load\n";
      var_dump($args);
      die;
    }
  }

  public function __get($fld)
  {
    switch ($fld)
    {
      case 'vout':

        if ( !($this->_vout instanceof TransactionOutput) ) {
          $this->_vout = $this->bc->transactions->getvout($this->txid, $this->n);
        }

        return $this->_vout;

      break;

      default:

      break;
    }
  }

  public function stdClass()
  {
    $arr = [
      "txid" => $this->txid,
      "n" => $this->n
    ];

    if ($this->_vout instanceof TransactionOutput) {
      $arr['vout'] = $this->_vout->stdClass();
    }

    return (object) $arr;

  }

  private function _loadArray($arr)
  {

    if (false === $arr instanceof \stdClass) {
      $arr = (object) $arr;
    }

    foreach (["scriptSig", "txid", "sequence"] as $fld) {
      $this->{$fld} = $arr->{$fld};
    }

    if (is_numeric($arr->vout)) { //todo: consider not loading until triggered by __get
      $this->_vout = $this->bc->transactions->getvout($arr->txid, $arr->vout);
    } else if (is_array($arr->vout) || $arr->vout instanceof \stdClass) {
      $this->_vout = new TransactionOutput($this->bc, $arr->vout);
    }
  }
}




//two of em