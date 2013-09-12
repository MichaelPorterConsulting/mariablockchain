<?php

namespace WillGriffin\MariaBlockChain;

require_once "BlockChain.php";
require_once "TransactionInput.php";
require_once "TransactionOutput.php";


class Transaction extends BlockChainObject
{
  var $id;
  var $txid;

  var $raw;
  var $info;

  var $fullyloaded;

  public static $transactions;
  public static $maxScanDistance = 5;

  public function __construct($txid)
  {
    parent::__construct();
    $this->txid = $txid;
    $this->loaded = false;
    $this->fullyloaded = false;
  }

  /**
  *
  * get primary key id for transaction or creates if not yet in db. updates db if stale.. kinda greasy, love forthcoming
  *
  *
  * @param string txid transactions txid
  * @param boolean forceScan
  * @param integer distanceAway also scan in transactions this many degrees away
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
  //todo: whilst refactoring remember what the difference between forceScan and forceUpdate is and rename one
  public static function getID($txid, $forceScan = false, $distanceAway = 1, $forceUpdate = false)
  {
    self::log("Transaction::getID $txid");

    $timestarted = time();

    if ($distanceAway <= self::$maxScanDistance)
    {

      if (isset(self::$transactions[$txid]) && self::$transactions[$txid]["transaction_id"] > 0)
      {
        $transaction_id = self::$transactions[$txid]["transaction_id"];
        if ((!self::$transactions[$txid]['time'] || self::$transactions[$txid]['time'] == "0000-00-00 00:00:00" || self::$transactions[$txid]['time'] == "1970-01-01 00:00:00") && self::$transactions[$txid]['lastScanned'] < $timestarted - 10)
        {
          echo "No time and not checked in the past 10 seconds, forcing update\n";
          BlockChain::log("No time and not checked in the past 10 seconds, trying to update");
          $forceUpdate = true;
        }
      } else {
        $idsql = 'select transaction_id, time from transactions where txid = "'.$txid.'"';
        $trow = BlockChain::$db->assoc($idsql);
        if ($trow && $trow['transaction_id'] > 0)
        {
          $transaction_id = $trow['transaction_id'];
          $transaction_dbtime = $trow['time'];

          echo "tx: $transaction_id $transaction_dbtime $txid\n";

          if (!$transaction_dbtime || $transaction_dbtime == "0000-00-00 00:00:00" || $transaction_dbtime == "1970-01-01 00:00:00")
          {
            echo "forcing update for $txid";
            BlockChain::log("Forcing update for $txid");
            $forceUpdate = true;
          }
        } else {
          BlockChain::log("transaction not found");
          $transaction_id = false;
        }
      }

      if (!$transaction_id)
      {
        $info = array();

        $info = (object)array_merge((array)$info, (array)self::getRawInfo($txid));

        if ($info->confirmations == 0)
        {
          $walletinfo = self::getInfo($txid);
          $info->time = $walletInfo->time;
        }

        //var_dump($info);
         //Transaction::log("Inserting transaction $txid");

        $insertTransactionSQL = 'insert into transactions (account, address, category, amount, confirmations, blockhash, blockindex, blocktime, txid, time, timereceived, inwallet) values ("'.$info->account.'","'.$info->address.'","'.$info->category.'","'.$info->amount.'","'.$info->confirmations.'","'.$info->blockhash.'","'.$info->blockindex.'","'.date("Y-m-d H:i:s",$info->blocktime).'","'.$info->txid.'","'.date("Y-m-d H:i:s",$info->time).'","'.date("Y-m-d H:i:s",$info->timereceived).'", '.intval($info->inwallet).')';
        //echo $insertTransactionSQL."\n\n";
        $transaction_id = BlockChain::$db->insert($insertTransactionSQL);

        if (count($info->vin))
          self::vInScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vin, $distanceAway);

        if (count($info->vout))
          self::vOutScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vout);

        if ($info->inwallet)
          self::detailsScan($txid);

        self::$transactions[$txid] = array(
          "transaction_id" => $transaction_id,
          "time" => $info->time,
          "txid" => $txid,
          "ismine" => $ismine,
          "info" => $info
        );

      } else {
        //$info = self::getInfo($txid);

        if ($forceUpdate)
        {
          echo "doing update\n";
          $info = array();
          if ($ismine)
            $info = self::getInfo($txid);

          $info = (object)array_merge((array)$info, (array)self::getRawInfo($txid, true));

          $infoFields = array('account','address','category','amount','confirmations','blockhash','blockindex','blocktime','timereceived','inwallet');
          foreach ($infoFields as $fld)
          {
            if (!empty($info->{$fld}) && $info->{$fld} != self::$transactions[$txid][$fld])
            {
              $updateFields .= "$fld = '{$info->{$fld}}',";
              self::$transactions[$txid][$fld] = $info->{$fld};
            }
          }

          if ($info->time && $info->time != self::$transactions[$txid]['info']->time)
          {
            echo $info->time.",".self::$transactions[$txid]['info']->time."\n";
            self::$transactions[$txid]['time'] = date("Y-m-d H:i:s", $info->time);
            $updateFields .= "time = '".date("Y-m-d H:i:s", $info->time)."',";
          }

          if (!empty($updateFields))
          {
            $updateFields = rtrim($updateFields,',');
            $usql = "update transactions set $updateFields where transaction_id = $transaction_id";
            BlockChain::$db->update($usql);
            echo $usql;
            $broadcast = true;
          }

          self::$transactions[$txid]['info'] = $info;

          BlockChain::log($usql);

        }

        if ($forceScan)
        {
          if (count($info->vin))
            self::vInScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vin, $distanceAway);

          if (count($info->vout))
            self::vOutScan((object)['transaction_id' => $transaction_id, 'txid' => $info->txid], $info->vout);
        }
      }
      self::$transactions[$txid]['lastScanned'] = time();

      if ($broadcast)
        self::broadcastUpdate($transaction_id);

      //echo "transaction_id: $transaction_id\n";
      return $transaction_id;
    } else {
      return false;
    }
  }

  //todo: looks like garbage, toast it
  public static function getTransactionAddresses($transaction_id)
  {
    $addresses = array();
    $isql = "select addresses.address as address, transactions_vouts.value as amount, transactions.confirmations as confirmations, transactions.txid as txid, transactions.time as txtime from transactions inner join transactions_vouts on transactions.transaction_id = transactions_vouts.transaction_id inner join transactions_vouts_addresses on transactions_vouts.vout_id = transactions_vouts_addresses.vout_id inner join addresses on transactions_vouts_addresses.address_id = addresses.address_id where transactions_vouts.transaction_id = $transaction_id";
    $ilist = BlockChain::$db->column($isql);
    if (count($ilist) > 0)
      $addresses = $ilist;

    $isql = "select addresses.address as address from transactions_vins inner join transactions_vouts on transactions_vins.vout_id = transactions_vouts.vout_id inner join transactions_vouts_addresses on transactions_vouts.address_id = addresses.address_id where transactions_vouts.transaction_id = $transaction_id";
    $ilist = BlockChain::$db->column($isql);
    if (count($ilist) > 0)
      $addresses = $ilist;

  }

  //todo: toast this and replace functionality by setting the onUpdate lambda when initializing
  public static function broadcastUpdate($transaction_id)
  {
    echo "Broadcasting updates\n";
    self::log("Address::getLedger $address $since");
    $ledgerSQL = "select addresses.address as address, addresses_ledger.ledger_id as ledger_id, addresses_ledger.amount as amount, transactions.txid as txid, transactions.time as txtime, transactions.confirmations as confirmations from addresses inner join addresses_ledger on addresses.address_id = addresses_ledger.address_id inner join transactions on addresses_ledger.transaction_id = transactions.transaction_id where transactions.transaction_id = $transaction_id";

    $entries = BlockChain::$db->assocs($ledgerSQL);
    foreach ($entries as $entry)
    {

      echo "Checking for clients in the ".$entry['address']." channel\n";
      $csql = "select count(*) from websockets_clients_channels inner join websockets_channels on websockets_clients_channels.socket_channel_id = websockets_channels.socket_channel_id where websockets_channels.name = '".$entry['address']."'";
      if (BlockChain::$db->value($csql) > 0)
      {
        echo "found, broadcasting update\n";
        //BlockChain::broadcast(json_encode($entry), $entry['address']);
      }
    }
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
  public static function getInfo($txid, $ismine = false)
  {
    self::log("Transaction::getInfo $txid");
    $info = BlockChain::$bitcoin->gettransaction($txid);
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
  public static function getRawInfo($txid, $forceUpdate = false)
  {
    self::log("Transaction::getRawInfo $txid");
    if (count(self::$transactions[$txid]) > 0 && $forceUpdate == false)
    {
      return self::$transactions[$txid]['info'];
    } else {
      return BlockChain::$bitcoin->getrawtransaction($txid, 1);
    }
  }


  /**
  *
  * meh
  *
  *
  * @param string txid transaction txid
  *
  * <code>
  * <?php
  *
  * $txRaw = Transaction::getRawInfo('foobar');
  *
  * ?>
  * </code>
   */

  //todo: i get why i wrote it but don't see the point in keeping it, kill when safe
  public static function detailsScan($txid)
  {
    self::log("Transaction::detailsScan $txid");
    $info = self::getInfo($txid); //todo: i bet this is going to break 
    $transaction_id = self::getID($txid);
    foreach ($info->details as $detail)
    {
      $account_id = Account::getID($detail['account']);
      $address_id = Address::getID($detail['address']);
      $amount = floatval($detail['amount']);
      $fee = floatval($detail['fee']);

      $detail_id = BlockChain::$db->value("select detail_id from transactions_details where transaction_id = $transaction_id and address_id = $address_id and amount = $amount");
      if (!$detail_id)
      {
        $detail_id = BlockChain::$db->insert("insert into transactions_details (transaction_id, account_id, address_id, amount, fee) values ($transaction_id, $account_id, $address_id, $amount, $fee)");
      }
    }
  }

  /**
  *
  * ensures the transactions related vouts representation in the database is up to date
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
  public static function vOutScan($tx, $vouts)
  {
    self::log("Transaction::vOutScan {$tx->transaction_id} ".json_encode($vouts));
    $voutCount = BlockChain::$db->value("select count(*) from transactions_vouts where transaction_id = {$tx->transaction_id}");
    $voutFound = count($vouts);
    if ($voutFound > $voutCount)
    {
      foreach ($vouts as $vout)
      {
        $vout_id = TransactionOutput::getID($tx, $vout);
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
  public static function vInScan($tx, $vins, $distanceAway = 0)
  {
    self::log("Transaction::vInScan {$tx->transaction_id} ".json_encode($vins));
    $vinCount = BlockChain::$db->value("select count(*) from transactions_vins where transaction_id = {$tx->transaction_id}");
    $vinFound = count($vins);
    if ($vinFound > $vinCount)
    {
      foreach ($vins as $vin)
      {
        $vin_id = TransactionInput::getID($tx, $vin, $distanceAway++, ($distanceAway <= self::$maxScanDistance));
      }
    }
  }
}


