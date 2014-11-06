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

  public function __construct($blockchain) {

    parent::__construct($blockchain);

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
  * $transaction_id = Transaction::getID('foobar');
  *
  * ?>
  * </code>
   */

  //todo: refactor
  public function getID($txid, $forceScan = false, $depth = 1, $forceUpdate = false) {

    $this->trace("Transaction::getID $txid");
    $updated = false;
    $timestarted = time();

    if ($depth <= $this->maxDepth)
    {

      $idsql = 'select transaction_id from transactions where txid = ?';
      $transaction_id = $this->bc->db->value($idsql, ['s', $txid]);

      $this->trace("got transaction_id '$transaction_id'");

      if ( !is_numeric($transaction_id) ) {
        $this->trace("transaction_id not numeric, getting info");
        $info = $this->getInfo( $txid );
        $this->trace("getting info got".json_encode($info));

        $insertTransactionSQL = 'insert into transactions '.
          '(confirmations, '.
            'blockhash, '.
            'blocktime, '.
            'txid, '.
            'time, '.
            'inwallet'.
          ') values (?, ?, ?, ?, ?, ?)';

        if (!$info->time && !$info->blocktime) {
          $info->time = time();
        }

        $insertTransactionFlds = ['issssi',
            $info->confirmations,
            $info->blockhash,
            $info->blocktime,
            $info->txid,
            $info->time,
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
        $this->trace("update forced");

        $info = $this->getInfo($txid);
/*        foreach (['time', 'blocktime'] as $fld) {
          $info->{$fld} = date("Y-m-d H:i:s", $info->{$fld});
        }
        $this->trace("getting info");
*/
        $dbinfo = $this->bc->db->object( "select ".
          "confirmations, ".
          "blockhash, ".
          "blockindex, ".
          "txid, ".
          "time ".
          "from transactions where transaction_id = ?",
          ['i', $transaction_id]);

        $infoFields = [
          'confirmations' => 'i',
          'blockhash' => 's',
          'blockindex' => 'i',
          'blocktime' => 's',
          'inwallet' => 'i'];

        if (!$dbinfo->time) {
          $infoFields['time'] = 's';
        }

        foreach ($infoFields as $fld => $fldtype) {
          if (!empty($info->{$fld}) && $info->{$fld} != $this->transactions[$txid][$fld]) {

            $updateFields .= "$fld = ?,";
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
          $this->trace($usql);
          $this->trace(json_encode($updateValues));

          $this->bc->db->update( $usql, $updateValues );

          $updated = true;
        }

        //$this->transactions[$txid]['info'] = $info;

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

      //}
      //$this->transactions[$txid]['lastScanned'] = time();

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
  * $vout_id = TransactionOutput::getID(1, ...);
  *
  * </code>
  */
  public function getVinID($tx, $vin, $depth = 0, $followtx = true)
  {

    $this->trace("TransactionVin::getId $transaction_id".json_encode($vin));

    if ($vin->txid && isset($vin->vout)) {
      $vinsID = $vin->txid."-".$vin->vout;
      //if (isset($this->vins[$vinsID]) && $vins[$vinsID]['vin_id'] > 0) {

      //  $vin_id = $vins[$vinsID]['vin_id'];

      //} else {


      $this->trace("getting vin_id");
      $this->trace(json_encode([$tx->transaction_id, $vin->txid, $vin->vout]));

      $vin_id = $this->bc->db->value("select vin_id ".
        "from transactions_vins ".
        "where transaction_id = ? and txid = ? and vout = ?",
        ['isi', $tx->transaction_id, $vin->txid, $vin->vout]);

      if (!$vin_id) {
        if ($followtx) {
          $vinvout_transaction_id = $this->getID($vin->txid, false, $depth += 1);
          $vin_vout_id = $this->bc->db->value("select vout_id ".
            "from transactions_vouts ".
            "where transaction_id = ? and n = ?",
            ['ii', $vinvout_transaction_id, $vin->vout]);
        } else {
          $vin_vout_id = null;
        }

        $this->trace("inserting vin");
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
          (string)$vin->coinbase,
          $vin_vout_id];

        $this->trace($visql);
        $this->trace(json_encode($vivals));

        $vin_id = $this->bc->db->insert($visql, $vivals);

        //tag the corresponding vout as spent in this transaction
        $this->bc->db->update(
          "update transactions_vouts set spentat = ?, spentat_id = ? where txid = ? and vout = ?",
          ['sss',$tx->txid, $tx->transaction_id, $vin->txid, $vin->vout]);


          /* not using this
          if ($vin_vout_id)
          {
            $vinvoutaddresses = $this->bc->db->assocs("select transactions_vouts_addresses.address_id as address_id, transactions_vouts.value as amount from transactions_vouts_addresses inner join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id where transactions_vouts_addresses.vout_id = $vin_vout_id");
            if (count($vinvoutaddresses) > 0)
            {
              foreach ($vinvoutaddresses as $vinaddress)
              {
                  $aisql = "insert into addresses_ledger (transaction_id, vout_id, vin_id, address_id, amount) values ({$tx->transaction_id}, $vin_vout_id, $vin_id, ".$vinaddress['address_id'].", ".$vinaddress['amount']." * -1)";
                  $this->bc->db->insert($aisql);
              }
            }
          }*/

        //}
      }
    } else if ($vin->sequence > 0 && !empty($vin->coinbase)) { // Generation

      //$vinsID = $vin->txid."-".$vin->vout;
      //if (isset($this->vins[$vinsID]) && $vins[$vinsID]['vin_id'] > 0) {//todo: memcache
      //  $vin_id = $vins[$vinsID]['vin_id'];
      //} else {
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
      //}
    } else {
      $this->trace("can not compute input ".json_encode($vin));
    }

    //$this->vins["{$tx->transaction_id}-{$vin->vout}"]["vin_id"] = $vin_id;
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
  * $vout_id = TransactionOutput::getID(1, ...);
  *
  * </code>
   */
  public function getVoutID($tx, $vout)
  {

    $this->trace("TransactionVout::getId {$tx->transaction_id} {$vout->n}");
    //echo "processing vout\n";

    $this->trace( json_encode($tx) );


    //$voutsID = $tx->transaction_id."-".$vout->n;
    //if (isset($this->vouts["$voutsID"]) && $this->vouts["$voutsID"]['vout_id'] > 0) { //todo: memcache
    //  $vout_id = $this->vouts["$voutsID"]['vout_id'];
    //} else {

    $voutIDSQL = "select vout_id from transactions_vouts where transaction_id = ? and n = ?";
    $voutIDFlds = ['ii', $tx->transaction_id, $vout->n];
    $this->trace( $voutIDSQL );
    $this->trace( $voutIDFlds );
    $vout_id = $this->bc->db->value($voutIDSQL, $voutIDFlds);

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

      $this->trace( $vosql );
      $this->trace( json_encode($voflds) );

      $vout_id = $this->bc->db->insert($vosql, $voflds);

      $this->trace('got vout_id '.$vout_id);


      foreach ($vout->scriptPubKey->addresses as $address) {
        $this->trace("getting scriptPubkey address $address");
        $address = $this->bc->addresses->get($address);

        $aisql = "insert into transactions_vouts_addresses (vout_id, address_id) values (?, ?)";
        $this->bc->db->insert($aisql, ['ii', $vout_id, $address->address_id]);
      }

      //}
    }

    $this->trace("finished getting vout_id");

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
    $this->trace("Transaction::getInfo $txid");
    $info = $this->bc->rpc->getrawtransaction($txid, 1);


    try {
      $extendedInfo = $this->bc->rpc->gettransaction($txid);
    } catch (\Exception $e) {
      //echo $e->getMessage();
      $extendedInfo = false;
    }

    //var_dump($extendedInfo);

    if ($extendedInfo) {

      //echo "got extended info\n";
      //echo json_encode($extendedInfo);


      $info->time = $extendedInfo->time;
    }

    return $info;
  }


  /**
  *
  * retrieves the raw transaction
  *
  *
  * @param string txid transaction txid
  * @param boolean forceUpdate
  *
  * <code>
  * <?php
  *
  * $txRaw = Transaction::getRawInfo('foobar');
  *
  * ?>
  * </code>
   */

  //todo: this and getInfo are redundant toast one
  //public function getRawInfo($txid, $forceUpdate = false)
  //{
    //$this->trace("Transaction::getRawInfo $txid");
    //if (count($this->transactions[$txid]) > 0 && $forceUpdate == false)
    //{
    //  return $this->transactions[$txid]['info'];
    //} else {
    //return $this->bc->rpc->getrawtransaction($txid, 1);
    //}
  //}


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
    $this->trace("Transaction::vOutScan {$tx->transaction_id} ".json_encode($vouts));
    $voutCount = $this->bc->db->value(
      "select count(*) ".
        "from transactions_vouts ".
        "where transaction_id = ?",
      ['i', $tx->transaction_id]);

    $voutFound = count($vouts);
    if ($voutFound > $voutCount) {
      foreach ($vouts as $vout) {
        $vout_id = $this->getVoutID($tx, $vout);
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
    $this->trace("Transaction::vInScan {$tx->transaction_id} ".json_encode($vins));
    $vinCount = $this->bc->db->value("select count(*) ".
      "from transactions_vins where transaction_id = ?",
      ['i', $tx->transaction_id]);

    $vinFound = count($vins);
    if ($vinFound > $vinCount) {
      foreach ($vins as $vin) {
        $vin_id = $this->getVinID($tx, $vin, $depth++, ($depth <= $this->maxDepth));
      }
    }
  }




  public function get($txid, $requery = false)
  {
    $this->trace("Transaction::get $txid");
    $cached = $this->bc->cache->get("tx:$txid");

    $this->trace($cached);
    if ($cached !== false && $requery === false) {

      $this->trace("loading transaction from cache");
      //$this->trace($cached);
      $tx = new Transaction($this->bc, $cached);
      $this->trace('got transaction fine');
      //var_dump($tx);
      $this->trace( $tx );
    } else {
      $this->trace("loading transaction from txid");
      $tx = new Transaction($this->bc, $txid);
      $this->bc->cache->set( "tx:$txid", $tx->stdClass(), false, 60 );
    }

    return $tx;
  }

  public function getvout($txid, $n)
  {

    $cached = false;
    $cached = $this->bc->cache->get("$txid:$n");
    if ($cached !== false) {
      $vout = new TransactionOutput($this->bc, $cached);
    } else {

      $txinfo = $this->bc->transactions->getInfo($txid);
      $voutArr = $txinfo->vout[$n];
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
  * $vout_id = TransactionOutput::getID(1, ...);
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
  * $vout_id = TransactionOutput::getID(1, ...);
  *
  * </code>
  */

  public function listunspents($txid) {
    $this->trace(__METHOD__);
    $this->trace($txid);

    $tx = $this->get($txid, true);
    $unspents = [];

    $updated = false;

    foreach ($tx->vout as $vout) {

      if (empty($vout->spentat)) {

        $this->trace('found unspent');

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

          $this->trace($usql);
          $this->trace($uflds);

          $this->bc->db->update($usql, $uflds);

          $updated = true;

        } else { //no record in database indicating it was spent, return true

          $unspents[] = $vout;

        }

      }


      /* todo: confirm with blockchain before making logical decisions about spending */


    }

  }



}