<?php

namespace WillGriffin\MariaBlockChain;

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

  public function __construct( $rpc, $db, $cache ) {

    $this->db = $db;
    $this->rpc = $rpc;
    $this->cache = $cache;

    $this->transactions = new TransactionsController( $this );
    $this->addresses = new AddressesController( $this );
    $this->accounts = new AccountsController( $this );
    $this->blocks = new BlocksController( $this );

  }

  public function trace( $msg ) {
    if ($this->hasHook('trace')) {
      $this->emit( 'trace', $msg );
    } else {
      if (gettype($msg) !== "string") {
        $msg = json_encode($msg);
      }

      echo $msg."\n";
    }
  }

  public function error( $msg ) {
    if ($this->hasHook('error')) {
      $this->emit( 'error', $msg );
    } else {
      echo $msg."\n";
      die;
    }
  }
}