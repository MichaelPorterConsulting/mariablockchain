<?php
/**
 * Object
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Common.php";


/**
 *
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class Object extends Common {

  /**
	 * blockchain
	 * @var MariaBlockChain $bc blockchain the object is part of
	 * @since 0.1.0
	 */
  public $bc;

  /**
  * alias to bc->rpc
  * @var \nbobtc\bitcoind $rpc rpc connection
  * @since 0.1.0
  */
  public $rpc;

  /**
  * alias to bc->db
  * @var \MariaInterface\MariaInterface $db database interface
  * @since 0.1.0
  */
  public $db;

  /**
  * alias to bc->cache
  * @var \Memcache $cache memcache
  * @since 0.1.0
  */
  public $cache;

  /**
  * constructor
  * @name __construct
  * @param MariaBlockChain $blockchain blockchain scope
  * @since 0.1.0
  * @return void
  */
  public function __construct($blockchain)
  {
    $this->bc = $blockchain;
    $this->rpc = $this->bc->rpc;
    $this->db = $this->bc->db;
    $this->cache = $this->bc->cache;
  }

  /**
  * caches a serialized object
  * @name updateCached
  * @param string $what storage key
  * @param mixed $data storage value
  * @since 0.1.0
  * @return void
  */
  protected function updateCached($what, $data)
  {
    $this->currency->cache->set( $this->_cachePrefix.$what, $data, false, 60 );
  }

  /**
  * clears an entry from the cache
  * @name wipeCached
  * @param string $what storage key to clear
  * @since 0.1.0
  * @return void
  */
  protected function wipeCached($what)
  {
    $this->currency->cache->delete( $this->_cachePrefix.$what );
  }
}
