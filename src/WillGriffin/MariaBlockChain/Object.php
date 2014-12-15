<?php

namespace WillGriffin\MariaBlockChain;

require_once "Common.php";

class Object extends Common {

  //Connections
  public $bc;

  public $rpc;
  public $db;
  public $cache;

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
  public function __construct($blockchain)
  {
    $this->bc = $blockchain;
    $this->rpc = $this->bc->rpc;
    $this->db = $this->bc->db;
    $this->cache = $this->bc->cache;
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
    if ($this->hasHook( 'trace' )) {
      $this->emit( 'trace', $msg );
    } else {
      $this->bc->trace( $msg );
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
    if ($this->hasHook( 'error' )) {
      $this->emit( 'error', $msg );
    } else {
      $this->bc->error( $msg );
    }
  }

}