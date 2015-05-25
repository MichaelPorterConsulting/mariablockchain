<?php
/**
 * BlocksController
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Block.php";


/**
 *
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class BlocksController extends Object
{

  /**
  * constructor
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain scope
  * @since 0.1.0
  * @return void
  */
  public function __construct($blockchain)
  {
    parent::__construct($blockchain);
  }

  /**
  * Gets the primary key in the database for a block, inserts a new record if need be
  * @name getId
  * @param string|stdClass $block either blockhash or stdClass as returned by rpc to get id for
  * @since 0.1.0
  * @return int database primary key for block
  *
  * <code>
  * $address_id = $blockchain->blocks->getId('00000000000000000400d9582bab30043c7f582892f234fedf7cc5cea88107af');
  * </code>
  */
  public function getId($block)
  {
    if (gettype($block) === "string") {
      $block = $this->bc->rpc->getblock($block);
    }

    $block_id = $this->bc->db->value("select block_id ".
      "from blocks ".
      "where hash = ?",
      ['s', $block->hash]);

    if (!$block_id) {
      $block_id = $this->insertBlock($block, false);
    }

    return $block_id;
  }


  /**
  * insert block into the database, optionally all it's transactions too
  * way to slow, raw block parsing is a priority
  * @name insertBlock
  * @param string|stdClass $block either blockhash or stdClass as returned by rpc to insert
  * @param boolean $insertTransactions whether to add all the transactions in the block as well
  * @since 0.1.0
  * @return int new block_id
  */
  public function insertBlock($block, $insertTransactions = true)
  {
    $this->trace(__METHOD__);
    if (gettype($block) === "string") {
      $block = $this->bc->rpc->getblock("$block");
    }

    if ($block->hash) {

      $bsql = "insert into blocks ".
        "(time, ".
        "hash, ".
        "size, ".
        "height, ".
        "version, ".
        "merkleroot, ".
        "nonce, ".
        "bits, ".
        "difficulty, ".
        "previousblockhash, ".
        "nextblockhash, ".
        "last_updated ".
      ") values (from_unixtime(?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now())";

      $bvals = ['isiidsisdss',
        $block->time,               //i
        $block->hash,               //s
        $block->size,               //i
        $block->height,             //i
        $block->version,            //d
        $block->merkleroot,         //s
        $block->nonce,              //i
        $block->bits,               //s
        $block->difficulty,         //d
        $block->previousblockhash,  //s
        $block->nextblockhash       //s
      ];

      $block_id = $this->bc->db->insert($bsql, $bvals);

      if ($insertTransactions === true) {

        if ($block->height > 0) {
          $transaction_ids = $this->getTransactionIds($block);
        } else { //insert genesis block special
          $insertTransactionSQL = 'insert into transactions '.
            '('.
              'blockhash, '.
              'blocktime, '.
              'txid, '.
              'time, '.
              'inwallet'.
            ') values (?, ?, ?, ?, ?)';

          $insertTransactionFlds = ['ssssi',
            $block->blockhash,
            $block->time,
            $block->tx[0],
            $block->time,
            0
          ];

          $transaction_ids = [$this->bc->db->insert($insertTransactionSQL, $insertTransactionFlds)];
        }

        foreach ($transaction_ids as $transaction_id) {
          $block_transaction_id = $this->bc->db->insert("insert ".
            "into blocks_transactions ".
            "(block_id, transaction_id) ".
            "values (?, ?)",
            ['ii', $block_id, $transaction_id]);
        }
      }
    } else {
      $this->error('invalid argument for insertBlock');
    }

    return $block_id;
  }


  /**
  * gets transaction_ids, inserting them into db if not already existent
  * way to slow, raw block parsing is a priority
  * @name getTransactionIds
  * @param string|stdClass $block either blockhash or stdClass as returned by rpc to insert
  * @param boolean $update force transactions to update
  * @since 0.1.0
  * @return array list of transaction_ids
  */
  public function getTransactionIds($block, $update = false)
  {
    $this->trace(__METHOD__);

    if (gettype($block) === "string") {
      $block = $this->bc->rpc->getblock("$block");
    }

    $transaction_ids = [];
    if ($block->hash && count($block->tx)) {
      foreach ($block->tx as $txid) {
        $transaction_ids[] = $this->bc->transactions->getId($txid, true, 0, $update);
      }
    }

    return $transaction_ids;
  }

  /**
  * retrieve a \Block with caching
  * @name get
  * @param string $blockhash blockhash of the block to retrieve
  * @param boolean $refresh if true forces refreshing of entry in cache
  * @since 0.1.0
  * @return \MariaBlockChain\Block
  *
  * <code>
  * $block = $blockchain->block->get('00000000000000000400d9582bab30043c7f582892f234fedf7cc5cea88107af');
  * </code>
  */
  public function get($blockhash, $refresh = false)
  {
    $this->trace(__METHOD__." $blockhash");
    $cached = $this->bc->cache->get("block:$blockhash");

    if ($cached !== false && $refresh === false) {
      $block = new Block($this->bc, $cached);
    } else {
      $this->trace("loading block from blockhash");
      $block = new Block($this->bc, $blockhash);
      $this->bc->cache->set( "block:$blockhash", $block->stdClass(), false, 60 );
    }

    return $block;
  }

  /**
  * retrieves info about a block, redundant -- toast
  * @name get
  * @param string blockhash block hash
  * @since 0.1.0
  * @return \MariaBlockChain\Block
  *
  * <code>
  * $block = $blockchain->block->get('00000000000000000400d9582bab30043c7f582892f234fedf7cc5cea88107af');
  * </code>
  */
  public function getInfo($blockhash)
  {
    $info = $this->bc->rpc->getblock($blockhash);
    return $info;
  }

  public function getLast()
  {
    $lastHash = $this->bc->cache->get($this->bc->name.':lastblock');
    if (empty($lastHash)) {
      $lastHash = $this->bc->rpc->getbestblockhash();
      $this->setLast($lastHash);
    }
    return $lastHash
  }

  public function setLast($hash)
  {
    $this->bc->cache->set($this->bc->name.':lastblock', $hash);
  }

}
