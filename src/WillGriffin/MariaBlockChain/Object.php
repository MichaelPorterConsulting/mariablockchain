<?php

namespace WillGriffin\MariaBlockChain;

require_once "Common.php";

class Object extends Common {

  //Connections
  public $blockchain;

  public $rpc;
  public $db;
  public $cache;

  public function __construct($blockchain) {
    $this->blockchain = $blockchain;
    $this->rpc = $this->blockchain->rpc;
    $this->db = $this->blockchain->db;
    $this->cache = $this->blockchain->cache;
  }

  public function trace( $msg ) {
    if ($this->hasHook( 'trace' )) {
      $this->emit( 'trace', $msg );
    } else {
      $this->blockchain->trace( $msg );
    }
  }

  public function error( $msg ) {
    if ($this->hasHook( 'error' )) {
      $this->emit( 'error', $msg );
    } else {
      $this->blockchain->error( $msg );
    }
  }

}