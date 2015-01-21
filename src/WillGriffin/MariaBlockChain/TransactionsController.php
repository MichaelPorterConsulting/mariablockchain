<?php

namespace WillGriffin\MariaBlockChain;

require_once "Transaction.php";
require_once "TransactionInput.php";
require_once "TransactionOutput.php";

class TransactionsController extends Object
{

  public $transactions; //todo: replace with memcache
  public $vins; //todo: replace with memcache
  public $vouts; //todo: replace with memcache

  public $maxDepth = 5;

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
  public function decoderaw($raw)
  {

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

    if (!$info->time) {
      $info->time = time();
    }

    return $info;

  }

  /**
  *
  * get primary key id for transaction or creates if not yet in db. updates db if stale.. kinda greasy, love forthcoming
  *
  *
  * @param string txid transactions txid
  * @param boolean forceScan
  * @param integer depth also scan in transactions this many degrees away
  * @param string forceUpdate
  *
  *
  * <code>
  * <?php
  *
  * $transaction_id = Transaction::getId('foobar');
  *
  * ?>
  * </code>
   */

  //todo: refactor
  public function getId($tx, $forceScan = false, $depth = 1, $forceUpdate = false)
  {

    $this->trace(__METHOD__." ".json_encode($tx));
    $updated = false;
    $timestarted = time();

    $info = false;

    if ($depth <= $this->maxDepth)
    {

      if (is_string($tx)) { //if txstr is a raw transaction
        $txid = $tx;
      } else if ($tx instanceof \stdClass && is_string($tx->txid)) {
        $txid = $tx->txid;
        $info = $tx;
      } else {
        $this->trace("error: invalid transaction");
        $this->error("invalid transaction");
        die;
      }

      $idsql = 'select transaction_id from transactions where txid = ?';
      $transaction_id = $this->bc->db->value($idsql, ['s', $txid]);

      if ( !is_numeric($transaction_id) ) {

        if (!$info) {
          $info = $this->getInfo( $txid );
          $this->trace($info);
        }

        $insertTransactionSQL = 'insert into transactions '.
          '(confirmations, '.
            'blockhash, '.
            'blocktime, '.
            'txid, '.
            'time, '.
            'inwallet'.
          ') values (?, ?, from_unixtime(?), ?, from_unixtime(?), ?)';

        if (!$info->time && !$info->blocktime) {
          $info->time = time();
        }

        $insertTransactionFlds = ['isisii',
            intval($info->confirmations),
            $info->blockhash,
            intval($info->blocktime),
            $info->txid,
            intval($info->time),
            intval($info->inwallet)
          ];


        $transaction_id = $this->bc->db->insert( $insertTransactionSQL, $insertTransactionFlds );

        if ($transaction_id > 0) {
          if (count($info->vin)) //todo: how exactly could this not be? what's the point of this 'if', worth throwing error ? maybe a mined transaction has no inputs?
            $this->vInScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vin, $depth);

          if (count($info->vout))//todo: ^^
            $this->vOutScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vout);

        } else {
          $this->trace("insert failed ($transaction_id)");
          $this->trace( $insertTransactionSQL );
          $this->trace( json_encode($insertTransactionFlds) );
        }

      }

      if ($forceUpdate) {
        if ($info === false) {
          $info = $this->getInfo( $txid );
        }

        $dbinfo = $this->bc->db->object("select ".
          "confirmations, ".
          "blockhash, ".
          "txid, ".
          "unix_timestamp(blocktime) as blocktime, ".
          "unix_timestamp(time) as time ".
          "from transactions where transaction_id = ?",
          ['i', $transaction_id]);

        $infoFields = [
          'confirmations' => 'i',
          'blockhash' => 's',
          'blocktime' => 'i',
          'time' => 'i',
          'inwallet' => 'i'];

        foreach ($infoFields as $fld => $fldtype) {
          if (!empty($info->{$fld}) && $info->{$fld} != $this->transactions[$txid][$fld]) {

            if (in_array($fld, ['time','blocktime'])) {
              $updateFields .= "$fld = from_unixtime(?),";
            } else {
              $updateFields .= "$fld = ?,";
            }

            $updateValues[] = $info->{$fld};
            $updateTypes .=  $fldtype;

          }
        }

        if (!empty($updateFields)) {

          $updateFields = rtrim($updateFields,' ,');
          $updateValues[] = $transaction_id;
          $updateTypes .= 'i';

          array_unshift( $updateValues, $updateTypes );
          $usql = "update transactions set $updateFields where transaction_id = ?";
          $this->bc->db->update( $usql, $updateValues );
          $updated = true;
        }

      }


      //todo: still worthwhile ?
      if ($forceScan) {

        if (count($info->vin)) {
          $this->vInScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vin, $depth);
        }

        if (count($info->vout)) {
          $this->vOutScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vout);
        }

        $updated = true;
      }

      if ($updated) {
        $this->emit("updated", $txid);
      }

      return $transaction_id;
    } else {
      return false;
    }
  }


  /**
  *
  * get primary key id for transaction input. if not in the database inserts and grabs related tx
  *
  *
  * @param integer $transaction_id parent transaction primary key
  * @param array $vin associative array representing transaction input
  * @param integer distanceAway current recursion level
  * @param boolean $followtx whether to scan source transaction
  *
  * <code>
  *
  * $vout_id = TransactionOutput::getId(1, ...);
  *
  * </code>
  */
  public function getVinId($tx, $vin, $depth = 0, $followtx = true)
  {

    $this->trace(__METHOD__." $transaction_id ".json_encode($vin));

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
          "(transaction_id, txid, vout, asm, hex, sequence, coinbase, vout_id) ".
          "values (?, ?, ?, ?, ?, ?, ?, ?)";

        $vivals = ['isissisi',
          $tx->transaction_id,
          $vin->txid,
          $vin->vout,
          $vin->scriptSig->asm,
          $vin->scriptSig->hex,
          $vin->sequence,
          (string) $vin->coinbase,
          $vin_vout_id];

        //$this->trace($visql);
        //$this->trace(json_encode($vivals));

        $vin_id = $this->bc->db->insert($visql, $vivals);

        //tag the corresponding vout as spent in this transaction
        if ($vin_vout_id > 0) {
          $this->bc->db->update(
            "update transactions_vouts set spentat = ? where vout_id = ?",
            ['ss',$tx->txid, $vin_vout_id]);
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
  *
  * get primary key id for transaction output, inserts if not represented yet
  *
  *
  * @param integer $transaction_id parent transaction primary key
  * @param array $vout associative array representing vout
  *
  * <code>
  *
  * $vout_id = TransactionOutput::getId(1, ...);
  *
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
        "(transaction_id, txid, value, n, asm, hex, reqSigs, type)".
        " values (?, ?, ?, ?, ?, ?, ?, ?)";

      $voflds = ['isdissis',
        $tx->transaction_id,
        $tx->txid,
        $vout->value,
        $vout->n,
        $vout->scriptPubKey->asm,
        $vout->scriptPubKey->hex,
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
  *
  * retrieves info about a transaction
  *
  *
  * @param string txid transactions txid
  * @param boolean forceScan
  *
  * <code>
  * <?php
  *
  * $txInfo = Transaction::getInfo('foobar');
  *
  * ?>
  * </code>
   */

  public function getInfo($txid)
  {
    $this->trace(__METHOD__." $txid");
    $info = $this->bc->rpc->getrawtransaction($txid, 1);

    if (count($info->vin)) {
      $vinTotal = 0;
      foreach ($info->vin as $vin) {
        if ($vin->txid && $vin->vout) {

          $vtx = $this->bc->rpc->getrawtransaction($vin->txid, 1);
          $vv = $vtx->vout[$vin->vout];

          sort($vv->scriptPubKey->addresses);
          $vva = join(",", $vv->scriptPubKey->addresses);
          $vinVouts[$vva] = $vv;
          $vinTotal += $vv->value;
        }
      }
    }

    if (count($info->vout)) {
      foreach ($info->vout as $vout) {
        $voutTotal += $vout->value;
      }
    }

    $info->vinTotal = $vinTotal;
    $info->voutTotal = $voutTotal;

    if ($vinTotal > 0) { // if not a mined block, calc fee
      $info->fee = $vinTotal - $voutTotal;
    } else {
      $info->fee = 0;
    }

    if ($extendedInfo) {
      $info->time = $extendedInfo->time;
    }

    return $info;
  }

  /**
  *
  * ensures the transactions related vouts represented in the database are up to date
  *
  *
  * @param integer transaction_id transaction txid
  * @param array vouts array of associated vouts to add
  *
  * <code>
  * <?php
  *
  * $txRaw = Transaction::vOutScan('foobar');
  *
  * ?>
  * </code>
   */
  //todo: make second argument optional, if not set get vouts from bitcoind jsonrpc
  //todo: pointless or handy in a fork? if the latter needs to update the db if change detected
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
  *
  * ensures the transactions related vins representation in the database is up to date
  *
  *
  * @param integer transaction_id transaction txid
  * @param array vins array of associated vouts to add
  * @param integer distanceAway current recursion level
  *
  * <code>
  * <?php
  *
  * $txRaw = Transaction::vOutScan('foobar');
  *
  * ?>
  * </code>
   */
  //todo: make second argument optional, if not set get vins from bitcoind jsonrpc
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
  public function get($txid, $requery = false)
  {
    $this->trace(__METHOD__." $txid");
    $cached = $this->bc->cache->get("$txid");

    if ($cached !== false && $requery === false) {
      $tx = new Transaction($this->bc, $cached);
    } else {
      $this->trace("loading transaction from txid");
      $tx = new Transaction($this->bc, $txid);

      $this->bc->cache->set( "$txid", $tx->stdClass(), false, 60 );
    }

    return $tx;
  }

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
  *
  * roll a transaction back, for those that end up in a shitty fork
  *
  * @param integer $txid the transaction to roll back
  *
  * <code>
  *
  * $vout_id = TransactionOutput::getId(1, ...);
  *
  * </code>
  */

  public function rollback($txid) {

    $this->bc->db->update("update transactions_vouts set spentat = null where txid = ?", ['s', $txid]);
  }



  /**
  *
  *
  *
  * @param integer $txid checks and updates a transactions vouts and returns a list of those that are unspent
  *
  * <code>
  *
  * $vout_id = TransactionOutput::getId(1, ...);
  *
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

          // update the transaction_vout
          // this should on be needed in special circumstances and is here mostly just in case

          $usql = "update transactions_vouts set spentat = ? where txid = ? and n = ?";
          $uflds = ['ssi',$vin->txid, $tx->txid, $vout->n];

          $this->bc->db->update($usql, $uflds);
          $updated = true;

        } else { //no record in database indicating it was spent, return true

          $unspents[] = $vout;

        }
      }

      /* todo: confirm with blockchain before making decisions about spending */

    }
  }
}