<?php

namespace WillGriffin\MariaBlockChain;

require_once "Transaction.php";

class TransactionInput  extends Object
{

  protected $_vout;

  public $txid;
  public $n;

  public $scriptSig;
  public $sequence;

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
  public function __construct($blockchain, $args)
  {
    $this->bc = $blockchain;
    $this->trace(__METHOD__." ".json_encode($args));

    if (is_array($args) || ($args instanceof \stdClass)) {
      $this->_loadArray($args);
    } else {
      echo "\nCouldn't load\n";
      var_dump($args);
      die;
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
  public function __get($fld)
  {
    $this->trace(__METHOD__." ".$fld);
    switch ($fld)
    {
      case 'vout':

        if (is_string($this->txid) && is_numeric($this->n)){

          if ( !($this->_vout instanceof TransactionOutput)) {
            $this->_vout = $this->bc->transactions->getvout($this->txid, $this->n);
          }
        } else {
          $this->_vout = false;
        }

        return $this->_vout;

      break;

      default:

      break;
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
    $arr = [
      "txid" => $this->txid,
      "n" => $this->n
    ];

    if ($this->_vout instanceof TransactionOutput) {
      $arr['vout'] = $this->_vout->stdClass();
    }

    return (object) $arr;

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

    foreach (["scriptSig", "txid", "sequence","coinbase"] as $fld) {
      if ($arr->{$fld}) {
        $this->{$fld} = $arr->{$fld};
      }
    }

    if (is_numeric($arr->vout) && is_string($arr->txid)) { //todo: consider not loading until triggered by __get
      $this->trace("fetching vout from rpc");
      $this->n = $arr->vout;
      $this->_vout = $this->bc->transactions->getvout($arr->txid, $arr->vout);
    } else if (is_array($arr->vout) || $arr->vout instanceof \stdClass) {
      $this->trace("initalizing vout from array");
      $this->_vout = new TransactionOutput($this->bc, $arr->vout);
    } else {
      $this->trace("there is no vout");
    }
  }

}

