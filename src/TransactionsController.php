<?php
/**
 * TransactionsController
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Transaction.php";
require_once "TransactionInput.php";
require_once "TransactionOutput.php";

/**
 * Methods relating to transactions
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class TransactionsController extends Object
{
  //
  // /**
  // * A sample parameter
  // * @var int $myParam This is my parameter
  // * @since 0.1.0
  // */
  // public $transactions; //todo: replace with memcache
  //
  // /**
  // * A sample parameter
  // * @var int $myParam This is my parameter
  // * @since 0.1.0
  // */
  // public $vins; //todo: replace with memcache
  //
  // /**
  // * A sample parameter
  // * @var int $myParam This is my parameter
  // * @since 0.1.0
  // */
  // public $vouts; //todo: replace with memcache

  /**
  * how deep to cache in db
  * @var int $maxDepth max recursion level
  * @since 0.1.0
  */
  public $maxDepth = 5;

  /**
  *
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain scope
  * @param array $args additional arguments and overrides
  * @since 0.1.0
  * @return object
  */
  public function __construct($blockchain)
  {
    parent::__construct($blockchain);
  }

  /**
  * decode a raw transaction
  * @name decoderaw
  * @param string $raw transaction in hex form
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->decoderaw($txid);
  * </code>
  */
  public function decoderaw($raw)
  {

    $this->bc->rpc->disableAcceleration(); //todo: why? lack of block info and need to mimic rpc? fix
    $info = $this->bc->rpc->decoderawtransaction($raw);

    if (!$info->confirmations) {
      $info->confirmations = 0;
    }

    if (!$info->blocktime) {
      $info->blocktime = null;
    }

    if (!$info->blockhash) {
      $info->blockhash = null;
    }

    return $info;

  }

  /**
  * get primary key id for transaction or creates if not yet in db. updates db if stale. could definitely use a rewrite
  * @name getId
  * @param string $tx txid or raw transaction
  * @param boolean $forceScan force confirmation of db with rpc
  * @param int $depth what level of recursion we're currently at
  * @param boolean $forceUpdate force database update with rpc queried data
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $transaction_id = $blockchain->transactions->getId('foobar');
  * </code>
  */
  //todo: refactor
  public function getId($tx, $forceScan = false, $depth = 1, $forceUpdate = false)
  {

    $this->trace(__METHOD__);
    $updated = false; //if tx has been updated,
    $isFresh = false; //if tx has just been inserted don't bother updating
    $timestarted = time();
    $info = false;
    $fresh = false;

    if ($depth <= $this->maxDepth) {

      if (is_string($tx)) {
        $txid = $tx;
      } else if ($tx instanceof \stdClass && is_string($tx->txid)) {
        //for those days when the wallet server wants to send you a raw transaction
        //your local bitcoind node might not know about yet
        $txid = $tx->txid;
        $info = $tx;
      } else {
        throw new \InvalidArgumentException("can't read $tx");
      }

      $idsql = 'select transaction_id from transactions where txid = ?';
      $transaction_id = $this->bc->db->value($idsql, ['s', $txid]);

      if (!is_numeric($transaction_id)) {
        if (!is_object($info)) {
          try {
            $info = $this->bc->transactions->getInfo($txid);
          } catch (\Exception $e) {
            return false;
          }
          if (!$info) {
            return $info;
          }
        }

        $block = (isset($info->blockhash) && !empty($info->blockhash)) ? $this->bc->blocks->get($info->blockhash) : false;


        if (!$block) {
          $block = (object)[
            'hash' => "",
            'block_id' => 0,
            'time' => 0,
            'height' => 0
          ];
        }

        if (!$info->time) {
          if ($block->time) {
            $info->time = $block->time; //set to block time if availible
          } else {
            $info->time = time(); //assume it's not in a block yet
          }
        }

        $insertTransactionSQL = 'insert into transactions '.
          '(blockhash, '.
            'block_id, '.
            'txid, '.
            'time, '.
            'inwallet'.
          ') values (?, ?, ?, from_unixtime(?), ?)';

        $insertTransactionFlds = ['sisii',
          $block->hash,
          $block->block_id,
          $info->txid,
          intval($info->time),
          (isset($info->inwallet)) ? $info->inwallet : 0
        ];

        if (count($info->vout) > 0) {
          $transaction_id = $this->bc->db->insert( $insertTransactionSQL, $insertTransactionFlds );
        } else {
          throw new \Exception("Transactions must have at least one output\n".json_encode($info));
        }

        if ($transaction_id > 0) {

          if (count($info->vin))
            $this->vInScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vin, $depth);

          if (count($info->vout))
            $this->vOutScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vout);

        } else {
          //shouldn't be failing at this point
          throw new \Exception("Insert failed: $insertTransactionSQL");
        }

        $updated = true;
        $fresh = true;

      }

      if ($forceUpdate && !$fresh) {
        if ($info === false) {
          try {
            $info = $this->getInfo( $txid );
          } catch (\Exception $e) {
            $info = false;
          }
        }

        $dbinfo = $this->bc->db->object(
          "select ".
            "block_id, ".
            "blockhash, ".
            "txid, ".
            "unix_timestamp(time) as time ".
          "from transactions where transaction_id = ?",
          ['i', $transaction_id]);


        //pretty much the only bit of a transaction that will be update is once
        //confirmed the block it shows up in
        if (is_object($info) && is_object($dbinfo) && $info->blockhash !== $dbinfo->blockhash) {

          if (!isset($block) && isset($info->blockhash)) {
            $block = $this->bc->blocks->get($info->blockhash);
          }

          if ($info->time > $block->time) {
            $info->time = $block->time;
          }

          $info->height = $block->height;

          if (is_object($block) && is_numeric($block->block_id)) {
            $usql = "update transactions set block_id = ?, blockhash = ?, time = ?, last_updated = now() where transaction_id = ?";
            $this->bc->db->update( $usql, ['isii', $block->block_id, $block->hash, $info->time, $transaction_id] );
            $updated = true;
          }

        } else {
          if ($dbinfo && !$dbinfo->block_id) { //if unconfirmed prune with prejudice, otherwise leave to block control
            $this->prune($txid);
            $transactions_id = false;
          }
        }
      }

      if ($updated) {
        $this->emit("updated", $tx);
      }

      return $transaction_id;
    } else {
      return false;
    }
  }


  /**
  * get primary key id for transaction input. if not in the database inserts and grabs related transaction as well
  * @name getVinId
  * @param string $tx transaction in question
  * @param int $vin index in the transactions vin array
  * @param int $depth recursion depth
  * @param boolean $followtx if true get related transaction
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->getvout($txid);
  * </code>
  */
  public function getVinId($tx, $vin, $depth = 0, $followtx = true)
  {

    $this->trace(__METHOD__.":".json_encode($vin));
    if ($vin->txid && isset($vin->vout)) {
      $vinsId = $vin->txid."-".$vin->vout;

      $vin_id = $this->bc->db->value("select vin_id ".
        "from transactions_vins ".
        "where transaction_id = ? and txid = ? and vout = ?",
        ['isi', $tx->transaction_id, $vin->txid, $vin->vout]);

      if (!$vin_id) {
        if ($followtx) {
          $vinvout_transaction_id = $this->getId($vin->txid, false, $depth += 1);
          $vin_vout_id = $this->bc->db->value("select vout_id ".
            "from transactions_vouts ".
            "where transaction_id = ? and n = ?",
            ['ii', $vinvout_transaction_id, $vin->vout]);
        } else {
          $vin_vout_id = null;
        }

        //$this->trace("inserting vin");
        $visql = "insert into transactions_vins ".
          "(transaction_id, txid, vout, asm, vout_id) ".
          "values (?, ?, ?, ?, ?)";

        $vivals = ['isiii',
          $tx->transaction_id,
          $vin->txid,
          $vin->vout,
          $vin->sequence,
          $vin_vout_id];

        $vin_id = $this->bc->db->insert($visql, $vivals);

        //tag the corresponding vout as spent in this transaction
        if ($vin_vout_id > 0) {
          $this->bc->db->update(
            "update transactions_vouts set spentat = ? where vout_id = ?",
            ['si',$tx->txid, $vin_vout_id]);
        }

      }
    } else if ($vin->sequence > 0 && !empty($vin->coinbase)) { // Generation

      $vin_id = $this->bc->db->value("select vin_id ".
        "from transactions_vins ".
        "where transaction_id = ? and txid = ? and sequence = ? and coinbase = ?",
        ['isis', $tx->transaction_id, $vin->txid, $vin->sequence, $vin->coinbase]);

      if (!$vin_id) {
        $visql = "insert into transactions_vins ".
          "(transaction_id, sequence, coinbase, vout_id) ".
          "values (?, ?, ?, ?)";
        $vin_id = $this->bc->db->insert($visql, ['iisi', $tx->transaction_id, $vin->sequence, $vin->coinbase, 0]);
      }

    } else {
      $this->trace("can not compute input ".json_encode($vin));
    }

    return $vin_id;
  }

  /**
  * get primary key id for transaction output, inserts if not represented yet
  * @name getVoutId
  * @param Transaction $transaction_id parent transaction primary key
  * @param stdClass $vout associative array representing vout
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->getVoutId($tx, $vout);
  * </code>
  */
  public function getVoutId($tx, $vout)
  {

    $this->trace(__METHOD__." {$tx->transaction_id} {$vout->n}");

    $voutIdSQL = "select vout_id from transactions_vouts where transaction_id = ? and n = ?";
    $voutIdFlds = ['ii', $tx->transaction_id, $vout->n];

    $vout_id = $this->bc->db->value($voutIdSQL, $voutIdFlds);

    if (!$vout_id) {

      $vosql = "insert into transactions_vouts ".
        "(transaction_id, txid, value, n, hexgz, reqSigs, type)".
        " values (?, ?, ?, ?, ?, ?, ?)";

      $voflds = ['isiissis',
        $tx->transaction_id,
        $tx->txid,
        $vout->value,
        $vout->n,
        $hexgz,
        $vout->scriptPubKey->reqSigs,
        $vout->scriptPubKey->type];

      $vout_id = $this->bc->db->insert($vosql, $voflds);

      foreach ($vout->scriptPubKey->addresses as $address) {
        $address = $this->bc->addresses->get($address);

        $aisql = "insert into transactions_vouts_addresses (vout_id, address_id) values (?, ?)";
        $this->bc->db->insert($aisql, ['ii', $vout_id, $address->address_id]);
      }

    }

    return $vout_id;
  }




  /**
  * decoderaw/satoshiconvert
  * @name decode
  * @param string $raw decode raw transaction and convert values to satoshi
  * @since 0.1.0
  * @return stdClass
  *
  * <code>
  * $transaction = $blockchain->transactions->decode($raw);
  * </code>
  */
  public function decode($raw)
  {
    $tx = $this->bc->rpc->decoderawtransaction($raw);
    for ($x = 0; $x < count($tx->vout); $x++) {    //convert to satoshis
      $tx->vout[$x]->value = round($tx->vout[$x]->value * 1e8);
    }
    return $tx;
  }


  /**
  * getraw/decode wrapper
  * @name
  * @param string $txid transaction in question
  * @param int $n index in the transactions vout array
  * @since 0.1.0
  * @return stdClass|bool
  *
  * <code>
  * $tx = $blockchain->transactions->fetch($txid);
  * </code>
  */
  public function fetch($txid)
  {
    $raw = $this->bc->rpc->getrawtransaction($txid);
    $tx = $this->decode($raw); //for flexibility in decoding methods
    return json_decode(json_encode($tx)); //sigh
  }





  /**
  * retrieves info about a transaction
  * @name getInfo
  * @param string $txid transaction in question
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->getInfo($txid);
  * </code>
  */
  public function getInfo($txid)
  {
    $this->trace(__METHOD__." $txid");

    try {
      $info = $this->bc->rpc->getrawtransaction($txid, 1);
      if (count($info->vin)) {
        $vinTotal = 0;
        foreach ($info->vin as $vin) {
          if ($vin->txid && $vin->vout) {
            $vtx = $this->fetch($vin->txid);
            $vv = $vtx->vout[$vin->vout];
            sort($vv->scriptPubKey->addresses);// sorted for consistency
            $vva = join(",", $vv->scriptPubKey->addresses);
            $vinVouts[$vva] = $vv;
            $vinTotal += $vv->value;
          }
        }
      }

      if (count($info->vout)) {
        foreach ($info->vout as $vout) {
          if (is_float($vout->value)) {
            $vout->value = $this->toSatoshi($vout->value);
          }
          $voutTotal += $vout->value;
        }
      }

      if ($vinTotal > 0) { // if not a mined block, calc fee
        $info->fee = $vinTotal - $voutTotal;
      } else {
        $info->fee = 0;
      }
      return $info;

    } catch (\Exception $e) {
      return false;
    }
  }


  /**
  * ensures the transactions related vouts represented in the database is accurate
  * @name
  * @param Transaction transaction to scan
  * @param array vouts array of associated vouts to add
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->vOutScan($tx, $vouts);
  * </code>
  */
  //todo: make second argument optional, if not set get vouts from bitcoind jsonrpc
  //todo: pointless or handy in a fork? if the latter needs to update the db if change detected
  //      pretty sure this is overkill if not useless .. only thing that could change without a new txid is the transaction_id
  //      which will happen when a transaction pruned from a forked branch shows up again in the main with a new id
  public function vOutScan($tx, $vouts)
  {
    $this->trace(__METHOD__." {$tx->transaction_id} ");
    $voutCount = $this->bc->db->value(
      "select count(*) ".
        "from transactions_vouts ".
        "where transaction_id = ?",
      ['i', $tx->transaction_id]);

    $voutFound = count($vouts);
    if ($voutFound > $voutCount) {
      foreach ($vouts as $vout) {
        $vout_id = $this->getVoutId($tx, $vout);
      }
    }
  }

  /**
  * ensures the transactions vins representation in the database is accurate
  * @name vInScan
  * @param integer transaction_id transaction txid
  * @param array vins array of associated vouts to add
  * @param integer distanceAway current recursion level
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->vInScan($tx, $vins, $depth);
  * </code>
  */
  public function vInScan($tx, $vins, $depth = 0)
  {
    $this->trace(__METHOD__." {$tx->transaction_id}");
    $vinCount = $this->bc->db->value("select count(*) ".
      "from transactions_vins where transaction_id = ?",
      ['i', $tx->transaction_id]);

    $vinFound = count($vins);
    if ($vinFound > $vinCount) {
      foreach ($vins as $vin) {
        $vin_id = $this->getVinId($tx, $vin, $depth++, ($depth <= $this->maxDepth));
      }
    }
  }

  /**
  * retrieve a \Transaction with caching
  * @name get
  * @param string $txid txid to retrieve object for
  * @param boolean $refresh if true forces refreshing of entry in *memcache*, refreshing database is done with getId
  * @since 0.1.0
  * @return Transaction
  *
  * <code>
  * $address = $blockchain->addresses->get('85b11338cfa66ff8fd05810061809a43112cfb4698687ab02cce93482379e4d8', true);
  * </code>
  */
  public function get($txid, $refresh = false)
  {
    $this->trace(__METHOD__." $txid");
    $cached = $this->bc->cache->get("$txid");

    if ($cached !== false && $refresh === false) {
      $tx = new Transaction($this->bc, $cached);
    } else {
      $this->trace("loading transaction from txid");
      $tx = new Transaction($this->bc, $txid);

      $this->bc->cache->set( "$txid", $tx->stdClass(), false, 60 );
    }

    return $tx;
  }

  /**
  * retrieve an output by txid and index
  * @name getvout
  * @param string $txid transaction in question
  * @param int $n index in the transactions vout array
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->getvout('85b11338cfa66ff8fd05810061809a43112cfb4698687ab02cce93482379e4d8');
  * </code>
  */
  public function getvout($txid, $n)
  {
    $this->trace(__METHOD__." $txid $n");

    $cached = false;
    $cached = $this->bc->cache->get("$txid:$n");

    if ($cached !== false) {
      $vout = new TransactionOutput($this->bc, $cached);
    } else {

      $txinfo = $this->bc->transactions->getInfo($txid);
      $voutArr = $txinfo->vout[$n];
      $voutArr->txid = $txid;
      $vout = new TransactionOutput($this->bc, $voutArr);
    }

    return $vout;
  }

  /**
  * prunes a transaction and all it's descendants
  *
  * in the event we've cached a raw transaction from the wallet server that has
  * since proven to be invalid, clean it out of the database. also used to clean
  * up after a fork
  *
  * @name prune
  * @param string $txid transaction in question
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->prune('85b11338cfa66ff8fd05810061809a43112cfb4698687ab02cce93482379e4d8');
  * </code>
  */
  public function prune($txid)
  {
    $transaction_id = $this->bc->db->value("select transaction_id from transactions where  txid = ?", ['s', $txid]);
    if ($transaction_id) {

      $usql = "update transactions_vouts set spentat = null where txid = ?";
      $uvals = ['s', $txid];
      $this->bc->db->update($usql, $uvals);

      $inputSql = "select distinct spentat from transactions_vouts where transaction_id = ?";
      $inputVals = ['i', $transaction_id];
      $inputTxs = $this->bc->db->column($inputSql, 0, $inputVals);

      if (count($inputTxs) > 0) foreach ($inputTxs as $inputTx) {
        if ($inputTx)
          $this->prune($inputTx);
      }

      $this->bc->db->update("delete from transactions where transaction_id = ?", ['i', $transaction_id]);
      $this->bc->db->update("delete from transactions_vouts where transaction_id = ?", ['i', $transaction_id]);
      $this->bc->db->update("delete from transactions_vins where transaction_id = ?", ['i', $transaction_id]);
      $this->bc->cache->wipe($this->_cachePrefix.$txid);
    }
  }



  /**
  * checks and updates a transactions outputs and returns a list of those that are still unspent
  * @name listunspents
  * @param string $txid transaction in question
  * @param array $args properties to mix in
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $txUnspents = $blockchain->transactions->listunspent('85b11338cfa66ff8fd05810061809a43112cfb4698687ab02cce93482379e4d8');
  * </code>
  */
  public function listunspents($txid) {
    $this->trace(__METHOD__." $txid");

    $tx = $this->get($txid, true);
    $unspents = [];

    $updated = false;

    foreach ($tx->vout as $vout) {

      if (empty($vout->spentat)) {

        //$this->trace('found unspent');

        $vsql = 'select '.
            'vintx.txid as txid, '.
            'vintx.transaction_id as transaction_id '.
          'from transactions_vins '.
          'left join transactions as vintx on transactions_vins.transaction_id = vintx.transaction_id '.
          'where transactions_vins.txid = ? and transactions_vins.vout = ?';

        $vflds = ['si', $tx->txid, $vout->n];
        $vin = $this->bc->db->object($vsql, $vflds);

        if ($vin) {

          // ensure the output is marked as spent
          $usql = "update transactions_vouts set spentat = ? where txid = ? and n = ?";
          $uflds = ['ssi',$vin->txid, $tx->txid, $vout->n];

          $this->bc->db->update($usql, $uflds);
          $updated = true;

        } else { //no record in database indicating it was spent, return true
          $unspents[] = $vout;
        }
      }
    }
  }
}
