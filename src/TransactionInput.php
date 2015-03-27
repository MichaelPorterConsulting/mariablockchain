<?php
/**
 * TransactionInput
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Transaction.php";


/**
 * TransactionInput
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class TransactionInput  extends Object
{

  /**
  * transaction output
  * @var TransactionObject $_vout dynamically loaded transaction output object
  * @since 0.1.0
  */
  protected $_vout; // TransactionOutput, accessed though 'voutObj' property


  /**
  * output txid
  * @var string $txid transaction id of the corresponding output
  * @since 0.1.0
  */
  public $txid;

  /**
  * output index
  * @var int $vout output index
  * @since 0.1.0
  */
  public $vout;


  /**
  * scriptSig
  * @var int $scriptSig first half of the script
  * @since 0.1.0
  */
  public $scriptSig;

  /**
  * sequence
  * @var int $sequence priority if tx lock_time > 0
  * @since 0.1.0
  */
  public $sequence;

  /**
  * constructor
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain the blockchain scope
  * @param array $args properties to mix in
  * @since 0.1.0
  * @return void
  */
  public function __construct($blockchain, $args)
  {
    $this->bc = $blockchain;
    //$this->trace(__METHOD__." ".json_encode($args));

    if (is_array($args) || ($args instanceof \stdClass)) {
      $this->_loadArray($args);
    } else {
      $this->error("tried to initialze TransactionInput with invalid arguments");
    }
  }

  /**
  * load TransactionOutput when needed and alias index
  * @name __get
  * @param string $fld field to fetch
  * @since 0.1.0
  * @return void
  */
  public function __get($fld)
  {
    $this->trace(__METHOD__." ".$fld);
    switch ($fld) {

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

  /**
  * maintain alias
  * @name __get
  * @param string $fld field to set
  * @param array $val value to use
  * @since 0.1.0
  * @return void
  */
  public function __set($fld, $val)
  {
    switch ($fld) {
      case 'n':
        $this->vout = $val;
      break;
    }
  }


  /**
  * maintain alias
  * @name __get
  * @param string $fld field to set
  * @param array $val value to use
  * @since 0.1.0
  * @return void
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

  /**
  * returns voutObj
  * @name getVout
  * @since 0.1.0
  * @return TransactionOutput
  */
  public function getVout()
  {
    return $this->voutObj;
  }


  /**
  * mix in an array
  * @name _loadArray
  * @param array $arr field to set
  * @since 0.1.0
  * @return void
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
