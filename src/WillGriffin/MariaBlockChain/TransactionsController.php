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
  public function getID($txid, $forceScan = false, $depth = 1, $forceUpdate = false)
  {
    $this->trace("Transaction::getID $txid");
    $updated = false;
    $timestarted = time();

    if ($depth <= $this->maxDepth)
    {

      $idsql = 'select transaction_id from transactions where txid = ?';
      $transaction_id = $this->blockchain->db->value($idsql, ['s', $txid]);

      if ( !is_numeric($transaction_id) ) {
        $this->trace("transaction_id not numeric, getting info");
        $info = $this->getRawInfo( $txid );
        $this->trace("getting info got".json_encode($info));


        $insertTransactionSQL = 'insert into transactions '.
          '(confirmations, '.
            'blockhash, '.
            'blocktime, '.
            'txid, '.
            'time, '.
            'inwallet'.
          ') values (?, ?, ?, ?, ?, ?)';

        $insertTransactionFlds = ['issssi',
            $info->confirmations,
            $info->blockhash,
            date("Y-m-d H:i:s", $info->blocktime),
            $info->txid,
            date("Y-m-d H:i:s", $info->time),
            intval($info->inwallet)
          ];

        $transaction_id = $this->blockchain->db->insert( $insertTransactionSQL, $insertTransactionFlds );

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

        $info = $this->getRawInfo($txid, true);
        foreach (['time', 'blocktime'] as $fld) {
          $info->{$fld} = date("Y-m-d H:i:s", $info->{$fld});
        }

        $dbinfo = $this->blockchain->db->object( "select ".
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
          'time' => 's',
          'inwallet' => 'i'];

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
          $this->blockchain->db->update( $usql, $updateValues );

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

      $vin_id = $this->blockchain->db->value("select vin_id ".
        "from transactions_vins ".
        "where transaction_id = ? and txid = ? and vout = ?",
        ['isi', $tx->transaction_id, $vin->txid, $vin->vout]);

      if (!$vin_id) {
        if ($followtx) {
          $vinvout_transaction_id = $this->getID($vin->txid, false, $depth += 1);
          $vin_vout_id = $this->blockchain->db->value("select vout_id ".
            "from transactions_vouts ".
            "where transaction_id = ? and n = ?",
            ['ii', $vinvout_transaction_id, $vin->vout]);

        } else {
          $vin_vout_id = null;
        }

        $visql = "insert into transactions_vins ".
          "(transaction_id, txid, vout, asm, hex, sequence, coinbase, vout_id) ".
          "values (?, ?, ?, ?, ?, ?, ?, ?)";
        $vin_id = $this->blockchain->db->insert($visql, ['isissisi',
          $tx->transaction_id,
          $vin->txid,
          $vin->vout,
          $vin->scriptSig->asm,
          $vin->scriptSig->hex,
          $vin->sequence,
          $vin->coinbase,
          $vin_vout_id]);

          /* not using this
          if ($vin_vout_id)
          {
            $vinvoutaddresses = $this->blockchain->db->assocs("select transactions_vouts_addresses.address_id as address_id, transactions_vouts.value as amount from transactions_vouts_addresses inner join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id where transactions_vouts_addresses.vout_id = $vin_vout_id");
            if (count($vinvoutaddresses) > 0)
            {
              foreach ($vinvoutaddresses as $vinaddress)
              {
                  $aisql = "insert into addresses_ledger (transaction_id, vout_id, vin_id, address_id, amount) values ({$tx->transaction_id}, $vin_vout_id, $vin_id, ".$vinaddress['address_id'].", ".$vinaddress['amount']." * -1)";
                  $this->blockchain->db->insert($aisql);
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
      $vin_id = $this->blockchain->db->value("select vin_id ".
        "from transactions_vins ".
        "where transaction_id = ? and txid = ? and sequence = ? and coinbase = ?",
        ['isis', $tx->transaction_id, $vin->txid, $vin->sequence, $vin->coinbase]);

      if (!$vin_id) {
        $visql = "insert into transactions_vins ".
          "(transaction_id, sequence, coinbase, vout_id) ".
          "values (?, ?, ?, ?)";
        $vin_id = $this->blockchain->db->insert($visql, ['iisi', $tx->transaction_id, $vin->sequence, $vin->coinbase, 0]);
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
      $vout_id = $this->blockchain->db->value($voutIDSQL, ['ii', $tx->transaction_id, $vout->n]);

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

      $vout_id = $this->blockchain->db->insert($vosql, $voflds);

      foreach ($vout->scriptPubKey->addresses as $address) {
        $address_id = $this->blockchain->addresses->getID($address);

        $aisql = "insert into transactions_vouts_addresses (vout_id, address_id) values (?, ?)";
        $this->blockchain->db->insert($aisql, ['ii', $vout_id, $address_id]);

        $aisql = "insert into addresses_ledger (transaction_id, vout_id, address_id, amount) values (?, ?, ?, ?)";
        $this->blockchain->db->insert($aisql, ['iiid', $tx->transaction_id, $vout_id, $address_id, $vout->value]);

      }

      //}
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

  //todo: what's this ismine shit? toast it
  public function getInfo($txid, $ismine = false)
  {
    $this->trace("Transaction::getInfo $txid");
    $info = $this->blockchain->rpc->getrawtransaction($txid, 1);
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
  public function getRawInfo($txid, $forceUpdate = false)
  {
    $this->trace("Transaction::getRawInfo $txid");
    //if (count($this->transactions[$txid]) > 0 && $forceUpdate == false)
    //{
    //  return $this->transactions[$txid]['info'];
    //} else {
      return $this->blockchain->rpc->getrawtransaction($txid, 1);
    //}
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
  public function vOutScan($tx, $vouts)
  {
    $this->trace("Transaction::vOutScan {$tx->transaction_id} ".json_encode($vouts));
    $voutCount = $this->blockchain->db->value("select count(*) ".
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
    $vinCount = $this->blockchain->db->value("select count(*) ".
      "from transactions_vins where transaction_id = ?",
      ['i', $tx->transaction_id]);

    $vinFound = count($vins);
    if ($vinFound > $vinCount) {
      foreach ($vins as $vin) {
        $vin_id = $this->getVinID($tx, $vin, $depth++, ($depth <= $this->maxDepth));
      }
    }
  }




  public function get($txid)
  {
    $this->trace("Transaction::get $txid");
    $cached = $this->blockchain->cache->get("tx:$txid");
    if ($cached !== false) {

      $this->trace("loading transaction from cache");
      $this->trace($cached);
      $tx = new Transaction($this->blockchain, $cached);
    } else {
      $this->trace("loading transaction from txid");
      $tx = new Transaction($this->blockchain, $txid);
      $this->blockchain->cache->set( "tx:$txid", $tx->stdClass(), false, 60 );
      return $tx;
    }

    return $tx;
  }

  public function getvout($txid, $n)
  {

    $cached = false;
    $cached = $this->blockchain->cache->get("$txid:$n");
    if ($cached !== false) {
      $vout = new TransactionOutput($this->blockchain, $cached);
    } else {

      $txinfo = $this->blockchain->transactions->getInfo($txid);
      $voutArr = $txinfo->vout[$n];
      $vout = new TransactionOutput($this->blockchain, $voutArr);
    }

    return $vout;
  }



}