<?php

namespace willgriffin\MariaBlockChain;

require_once "Transaction.php";

class TransactionInput  extends Object
{

  protected $_vout; // TransactionOutput, accessed though 'voutObj' property

  public $txid;
  public $vout;

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
      //var_dump($args);
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
      case 'voutObj':

        if (is_string($this->txid) && is_numeric($this->vout)) {

          if ( !($this->_vout instanceof TransactionOutput)) {
            $this->_vout = $this->bc->transactions->getvout($this->txid, $this->vout);
          }
        } else {
          $this->_vout = false;
        }

        return $this->_vout;

      break;

      case 'n':
        return $this->vout;
      break;


      default:

      break;
    }
  }


  public function __set($fld, $val)
  {
    switch ($fld) {

      case 'n':
        $this->vout = $val;
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
      "vout" => $this->vout
    ];

    return (object) $arr;
  }

  public function getVout()
  {
    return $this->voutObj;
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
    $this->trace($arr);
    if (false === $arr instanceof \stdClass) {
      $arr = (object) $arr;
    }

    foreach (["scriptSig", "txid", "sequence","coinbase"] as $fld) {
      if (isset($arr->{$fld})) {
        $this->{$fld} = $arr->{$fld};
      }
    }

    if (isset($arr->vout) && is_numeric($arr->vout) && is_string($arr->txid)) {
      $this->trace("fetching vout from rpc");
      $this->n = $arr->vout;
      $this->_vout = $this->bc->transactions->getvout($arr->txid, $arr->vout);
    } else if (isset($arr->coinbase) && !empty($arr->coinbase)) {
      $this->trace("generation transaction");
      $this->trace($arr);
    } else {
      //$this->error("vin has no vout in _loadArray");
    }
  }
}
