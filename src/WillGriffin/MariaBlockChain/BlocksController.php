<?php

namespace WillGriffin\MariaBlockChain;

require_once "Block.php";

class BlocksController extends Object
{

  public function __construct($blockchain) {

    parent::__construct($blockchain);

  }

  public function getID($block)
  {

    if (gettype($block) === "string") {

      $block = $this->blockchain->rpc->getblock($block);
    }

    $this->trace("blockhash::getID {$block->hash}");
    $block_id = $this->blockchain->db->value("select block_id ".
      "from blocks ".
      "where hash = ?",
      ['s', $block->hash]);

    $this->trace("found block_id ($block_id)");

    if (!$block_id) {
      $this->trace("no love, creating ");
      $block_id = $this->insertBlock($block);
    }
    $this->trace("block_id $block_id");

    return $block_id;
  }

  public function insertBlock($block)
  {

    if (gettype($block) === "string") {
      $block = $this->blockchain->rpc->getblock("$block");
    }

    if ($block->hash) {
      $block_id = $this->blockchain->db->insert("insert into blocks ".
        "(hash, ".
          "confirmations, ".
          "size, ".
          "height, ".
          "version, ".
          "merkleroot, ".
          "time, ".
          "nonce, ".
          "bits, ".
          "difficulty, ".
          "previousblockhash, ".
          "nextblockhash, ".
          "last_update ".
        ") values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        ['siiidsiisdsss',
          $block->hash,               //s
          $block->confirmations,      //i
          $block->size,               //i
          $block->height,             //i
          $block->version,            //d
          $block->merkleroot,         //s
          $block->time,               //i
          $block->nonce,              //i
          $block->bits,               //s
          $block->difficulty,         //d
          $block->previousblockhash,  //s
          $block->nextblockhash,      //s
          $block->last_update         //s
        ]);

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
          date("Y-m-d H:i:s", $block->time),
          $block->tx[0],
          date("Y-m-d H:i:s", $block->time),
          0
        ];

        $transaction_ids = [$this->blockchain->db->insert($insertTransactionSQL, $insertTransactionFlds)];

      }




      foreach ($transaction_ids as $transaction_id) {
        $block_transaction_id = $this->blockchain->db->insert("insert ".
          "into blocks_transactions ".
          "(block_id, transaction_id) ".
          "values (?, ?)",
          ['ii', $block_id, $transaction_id]);
      }

    } else {
      $this->error('invalid argument for insertBlock');
    }

    return $block_id;
  }

  /*
  *
  *
  * gets transaction_ids, inserting them into db if not already existent
  *
  */
  public function getTransactionIds($block, $update = false)
  {
    $this->trace(__METHOD__);

    if (gettype($block) === "string") {
      $block = $this->blockchain->rpc->getblock("$block");
    }

    $transaction_ids = [];
    if ($block->hash && count($block->tx)) {
      foreach ($block->tx as $txid) {
        $transaction_ids[] = $this->blockchain->transactions->getID($txid, true, 0, $update);
      }
    }

    return $transaction_ids;
  }

}