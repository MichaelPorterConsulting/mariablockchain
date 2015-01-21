<?php

namespace WillGriffin\MariaBlockChain;

require_once "Transaction.php";

class Block extends Object {


  private $_vars;

  public $hash;
  public $confirmations;
  public $size;
  public $height;
  public $version;
  public $merkleroot;
  public $time;
  public $nonce;
  public $bits;
  public $difficulty;
  public $chainwork;
  public $previousblockhash;
  public $nextblockhash;

  public $tx;


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
  public function __construct($blockchain, $b)
  {
    $this->bc = $blockchain;
    $this->_vars = [];

    $this->trace(__METHOD__);

    if (is_numeric($b)) {
      $this->trace("loading block from id");
      $this->_vars['block_id'] = $b;
      //todo: $this->loadFromDatabase();
    } else if (is_string($b)) {
      $this->trace("loading block from string");
      $this->hash = $b;
      $this->_loadArray( $this->bc->blocks->getInfo($b) );
    } else if (is_array($b) || $b instanceof \stdClass) {
      $this->trace("loading block from array/object");
      $this->_loadArray($b);
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
  public function __get($var)
  {
    $this->trace(__METHOD__);
    switch ($var)
    {
      case 'block_id':

        if (!array_key_exists('block_id', $this->_vars)) {
          $this->bc->trace('getting block id');
          $this->_vars['block_id'] = $this->bc->blocks->getId($this->hash);
          $this->bc->trace(json_encode($this->_vars));
        }
        return $this->_vars['block_id'];

      break;

      default:
        $this->error("attempt to access illegal property of Block $var");
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
  public function __toString()
  {
    return $this->hash;
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
  public function json()
  {
    return json_encode( $this->stdClass() );
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
    return (object) $this->toArray();
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
  public function toArray()
  {
    $arr = [
      'hash' => $this->hash,
      'confirmations' => $this->confirmations,
      'size' => $this->size,
      'height' => $this->height,
      'version' => $this->version,
      'merkleroot' => $this->merkleroot,
      'time' => $this->time,
      'nonce' => $this->nonce,
      'bits' => $this->bits,
      'difficulty' => $this->difficulty,
      'chainwork' => $this->chainwork,
      'previousblockhash' => $this->previousblockhash,
      'nextblockhash' => $this->nextblockhash,
      'tx' => $this->tx
    ];
    return $arr;
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
    $this->trace("loading array");
    $this->trace($arr);

    if (is_array($arr)) {
      $arr = (object) $arr;
    }

    $this->hash = $arr->hash;
    $this->confirmations = $arr->confirmations;
    $this->size = $arr->size;
    $this->height = $arr->height;
    $this->version = $arr->version;
    $this->merkleroot = $arr->merkleroot;
    $this->time = $arr->time;
    $this->nonce = $arr->nonce;
    $this->bits = $arr->bits;
    $this->difficulty = $arr->difficulty;
    $this->chainwork = $arr->chainwork;
    $this->previousblockhash = $arr->previousblockhash;
    $this->nextblockhash = $arr->nextblockhash;

    foreach ($arr->tx as $tx) {
      $this->tx[] = $tx;
    }

  }

}
