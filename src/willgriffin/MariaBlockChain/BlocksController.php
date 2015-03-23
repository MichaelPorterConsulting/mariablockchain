<?php

namespace willgriffin\MariaBlockChain;

require_once "Block.php";

class BlocksController extends Object
{

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
  public function __construct($blockchain) {

    parent::__construct($blockchain);

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
  public function getId($block)
  {
    $this->trace(__METHOD__);
    if (gettype($block) === "string") {
      $block = $this->bc->rpc->getblock($block);
    }

    $this->trace("blockhash::getId {$block->hash}");
    $block_id = $this->bc->db->value("select block_id ".
      "from blocks ".
      "where hash = ?",
      ['s', $block->hash]);

    $this->trace("found block_id ($block_id)");

    if (!$block_id) {
      $this->trace("no love, creating ");
      $block_id = $this->insertBlock($block, false);
    }
    $this->trace("block_id $block_id");

    return $block_id;
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
            '(confirmations, '.
              'blockhash, '.
              'blocktime, '.
              'txid, '.
              'time, '.
              'inwallet'.
            ') values (?, ?, ?, ?, ?, ?)';

          $insertTransactionFlds = ['issssi',
            $block->confirmations,
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
  *
  *
  * gets transaction_ids, inserting them into db if not already existent
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
  public function get($blockhash, $requery = false)
  {
    $this->trace(__METHOD__." $blockhash");
    $cached = $this->bc->cache->get("block:$blockhash");

    if ($cached !== false && $requery === false) {

      $block = new Block($this->bc, $cached);

    } else {
      $this->trace("loading block from blockhash");
      $block = new Block($this->bc, $blockhash);
      $this->bc->cache->set( "block:$blockhash", $block->stdClass(), false, 60 );
    }

    return $block;
  }



  /**
  *
  * retrieves info about a block
  *
  *
  * @param string blockhash block hash
  *
  * <code>
  * <?php
  *
  * $blockInfo = Blocks::getInfo('foobar');
  *
  * ?>
  * </code>
   */

  public function getInfo($blockhash)
  {
    $this->trace(__METHOD__." $blockhash");
    $info = $this->bc->rpc->getblock($blockhash);

    return $info;
  }



}
