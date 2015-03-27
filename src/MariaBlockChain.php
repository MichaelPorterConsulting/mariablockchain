<?php
/**
 * MariaBlockChain
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Object.php";
require_once "AccountsController.php";
require_once "AddressesController.php";
require_once "TransactionsController.php";
require_once "BlocksController.php";

/**
 * The MariaBlockChain class
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class MariaBlockChain extends Common {

  /**
  * Database interface
  * @var \willgriffin\MariaInterface\MariaInterface $db database interface
  * @since 0.1.0
  */
  public $db;

  /**
  * bitcoind (etc) rpc via nbobtc/bitcoind-php fork at https://github.com/willgriffin/bitcoind-php
  * @var \Nbobtc\Bitcoind\Bitcoind $rpc bitcoind (etc etc) rpc connection
  * @since 0.1.0
  */
  public $rpc;

  /**
  * Memcached
  * @var \Memcache $cache Memcache connection
  * @since 0.1.0
  */
  public $cache;

  /**
  * Transactions Controller
  * @var \willgriffin\MariaBlockChain\TransactionsController $transactions transactions controller
  * @since 0.1.0
  */
  public $transactions;

  /**
  * Addresses Controller
  * @var \willgriffin\MariaBlockChain\AddressesController $addresses addresses controller
  * @since 0.1.0
  */
  public $addresses;

  /**
  * Accounts Controller
  * @var \willgriffin\MariaBlockChain\AccountsController $accounts accounts controller
  * @since 0.1.0
  */
  public $accounts;

  /**
  * Blocks Controller
  * @var \willgriffin\MariaBlockChain\BlocksController $blocks blocks controller
  * @since 0.1.0
  */
  public $blocks;

  /**
  * tracelog file name, option and overridden by any hooks
  * @var str $tracelog tracelog filename
  * @since 0.1.0
  */
  public $tracelog;

  /**
  * constructor
  * @name __construct
  * @param \Nbobtc\Bitcoind\Bitcoind $rpc bitcoind (etc etc) rpc connection
  * @param \willgriffin\MariaInterface\MariaInterface $db Database interface
  * @param \Memcache $cache Memcache connection
  * @param array $options additional options
  * @since 0.1.0
  * @return object
  *
  * <code>
  * //note, requirement is my accelerated fork of \nbobtc\bitcoind atm hence the magic_bytes arguments
  * $rpc = new \Nbobtc\Bitcoind\Bitcoind(new \Nbobtc\Bitcoind\Client(
  *   $rpcprotocol."://".$rpcuser.":".$rpcpass."@".$rpchost.":".$rpcport."/",
  *   null, //cert for ssl
  *   ($this->magic_byte) ? $this->magic_byte : false,
  *   ($this->magic_p2sh_byte) ? $this->magic_p2sh_byte: false
  * ));
  *
  * $cache = new \Memcache;
  * $cache->connect($memcachehost, $memcacheport);
  *
  * $db = new \willgriffin\MariaInterface\MariaInterface (array(
  *   "host" => $dbhost,
  *   "user" => $dbuser,
  *   "pass" => $dbpass,
  *   "port" => $dbport,
  *   "name" => $dbname
  * ));
  *
  * $bitcoin = new MariaBlockChain($rpc, $db, $cache);
  * $bitcoin->addresses->get($bitcoinAddress);
  * $bitcoin->addresses->getLedger($bitcoinAddress);
  * $bitcoin->transactions->get($txid);
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
  * Add an entry to the tracelog
  * @name trace
  * @param str $msg message to trace
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $blockchain->trace('something happened');
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
  * Error handler
  * @name error
  * @param str $msg an error message
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $blockchain->error('something bad happened');
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
