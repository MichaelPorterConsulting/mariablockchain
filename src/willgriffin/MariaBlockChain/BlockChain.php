<?php

namespace willgriffin\MariaBlockChain;

require_once "Object.php";
require_once "AddressesController.php";
require_once "AccountsController.php";
require_once "TransactionsController.php";
require_once "BlocksController.php";

class BlockChain extends Common {

  //Connections
  public $db;       //MariaInterface
  public $rpc;      //server rpc connection
  public $cache;    //Memcache

  //Controllers
  public $transactions;
  public $addresses;
  public $accounts;
  public $blocks;

  public $tracelog;

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
  public function __construct( $rpc, $db, $cache, $options = [] )
  {

    if (isset($options['tracelog'])) {
      $this->tracelog = $options['tracelog'];
    } else {
      $this->tracelog = false;
    }

    $this->db = $db;
    $this->rpc = $rpc;
    $this->cache = $cache;

    $this->transactions = new TransactionsController( $this );
    $this->addresses = new AddressesController( $this );
    $this->accounts = new AccountsController( $this );
    $this->blocks = new BlocksController( $this );
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
  public function trace( $msg )
  {
    if ($this->tracelog === true) {
      if ($this->hasHook('trace')) {
        $this->emit( 'trace', $msg );
      } else {
        if (gettype($msg) !== "string") {
          $msg = json_encode($msg);
        }

        echo $msg."\n";
      }
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
  public function error( $msg )
  {
    if ($this->hasHook('error')) {
      $this->emit('error', $msg);
      die;
    } else {
      throw new \Exception($msg);
    }
  }
}
