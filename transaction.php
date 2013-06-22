<?

require_once "myblockchain.php";


class Transaction extends MyBlockChainRecord
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

  public function __get($fld)
  {
    return $this->full['fld'];
  }

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
          MyBlockChain::log("No time and not checked in the past 10 seconds, trying to update");
          $forceUpdate = true;
        }
      } else {
        $idsql = 'select transaction_id, time from transactions where txid = "'.$txid.'"';
        $trow = MyBlockChain::$db->gethash($idsql);
        if ($trow && $trow['transaction_id'] > 0)
        {
          $transaction_id = $trow['transaction_id'];
          $transaction_dbtime = $trow['time'];

          echo "tx: $transaction_id $transaction_dbtime $txid\n";

          if (!$transaction_dbtime || $transaction_dbtime == "0000-00-00 00:00:00" || $transaction_dbtime == "1970-01-01 00:00:00")
          {
            echo "forcing update for $txid";
            MyBlockChain::log("Forcing update for $txid");
            $forceUpdate = true;
          }
        } else {
          MyBlockChain::log("transaction not found");
          $transaction_id = false;
        }
      }

      if (!$transaction_id)
      {
        $info = array();

        $info = array_merge($info, self::getRawInfo($txid));

        if ($info['confirmations'] == 0)
        {
          $walletinfo = self::getInfo($txid);
          $info['time'] = $walletinfo['time'];
        }

        //var_dump($info);
         //Transaction::log("Inserting transaction $txid");

        $insertTransactionSQL = 'insert into transactions (account, address, category, amount, confirmations, blockhash, blockindex, blocktime, txid, time, timereceived, inwallet) values ("'.$info['account'].'","'.$info['address'].'","'.$info['category'].'","'.$info['amount'].'","'.$info['confirmations'].'","'.$info['blockhash'].'","'.$info['blockindex'].'","'.date("Y-m-d H:i:s",$info['blocktime']).'","'.$info['txid'].'","'.date("Y-m-d H:i:s",$info['time']).'","'.date("Y-m-d H:i:s",$info['timereceived']).'", '.intval($info['inwallet']).')';
        //echo $insertTransactionSQL."\n\n";
        $transaction_id = MyBlockChain::$db->doinsert($insertTransactionSQL);

        if (count($info['vin']))
          self::vInScan($transaction_id, $info['vin'], $distanceAway);

        if (count($info['vout']))
          self::vOutScan($transaction_id, $info['vout']);

        if ($info['inwallet'])
          self::detailsScan($txid);

        self::$transactions[$txid] = array(
          "transaction_id" => $transaction_id,
          "time" => $info['time'],
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

          $info = array_merge($info, self::getRawInfo($txid, true));

          $infoFields = array('account','address','category','amount','confirmations','blockhash','blockindex','blocktime','timereceived','inwallet');
          foreach ($infoFields as $fld)
          {
            if (!empty($info[$fld]) && $info[$fld] != self::$transactions[$txid][$fld])
            {
              $updateFields .= "$fld = '{$info[$fld]}',";
              self::$transactions[$txid][$fld] = $info[$fld];
            }
          }

          if ($info['time'] && $info['time'] != self::$transactions[$txid]['info']['time'])
          {
            echo $info['time'].",".self::$transactions[$txid]['info']['time']."\n";
            self::$transactions[$txid]['time'] = date("Y-m-d H:i:s", $info['time']);
            $updateFields .= "time = '".date("Y-m-d H:i:s", $info['time'])."',";
          }

          if (!empty($updateFields))
          {
            $updateFields = rtrim($updateFields,',');
            $usql = "update transactions set $updateFields where transaction_id = $transaction_id";
            MyBlockChain::$db->doupdate($usql);
            echo $usql;
            $broadcast = true;
          }

          self::$transactions[$txid]['info'] = $info;

          MyBlockChain::log($usql);

        }

        if ($forceScan)
        {
          if (count($info['vin']))
            self::vInScan($transaction_id, $info['vin'], $distanceAway);

          if (count($info['vout']))
            self::vOutScan($transaction_id, $info['vout']);
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


  public static function getTransactionAddresses($transaction_id)
  {
    $addresses = array();
    $isql = "select addresses.address as address, transactions_vouts.value as amount, transactions.confirmations as confirmations, transactions.txid as txid, transactions.time as txtime from transactions inner join transactions_vouts on transactions.transaction_id = transactions_vouts.transaction_id inner join transactions_vouts_addresses on transactions_vouts.vout_id = transactions_vouts_addresses.vout_id inner join addresses on transactions_vouts_addresses.address_id = addresses.address_id where transactions_vouts.transaction_id = $transaction_id";
    $ilist = MyBlockChain::$db->getlist($isql);
    if (count($ilist) > 0)
      $addresses = $ilist;

    $isql = "select addresses.address as address from transactions_vins inner join transactions_vouts on transactions_vins.vout_id = transactions_vouts.vout_id inner join transactions_vouts_addresses on transactions_vouts.address_id = addresses.address_id where transactions_vouts.transaction_id = $transaction_id";
    $ilist = MyBlockChain::$db->getlist($isql);
    if (count($ilist) > 0)
      $addresses = $ilist;

  }

  public static function broadcastUpdate($transaction_id)
  {
    echo "Broadcasting updates\n";
    self::log("Address::getLedger $address $since");
    $ledgerSQL = "select addresses.address as address, addresses_ledger.ledger_id as ledger_id, addresses_ledger.amount as amount, transactions.txid as txid, transactions.time as txtime, transactions.confirmations as confirmations from addresses inner join addresses_ledger on addresses.address_id = addresses_ledger.address_id inner join transactions on addresses_ledger.transaction_id = transactions.transaction_id where transactions.transaction_id = $transaction_id";

    $entries = MyBlockChain::$db->gethashrows($ledgerSQL);
    foreach ($entries as $entry)
    {

      echo "Checking for clients in the ".$entry['address']." channel\n";
      $csql = "select count(*) from websockets_clients_channels inner join websockets_channels on websockets_clients_channels.socket_channel_id = websockets_channels.socket_channel_id where websockets_channels.name = '".$entry['address']."'";
      if (MyBlockChain::$db->getval($csql) > 0)
      {
        echo "found, broadcasting update\n";
        MyBlockChain::broadcast(json_encode($entry), $entry['address']);
      }
    }
  }

  public static function getInfo($txid, $ismine = false)
  {
    self::log("Transaction::getInfo $txid");
    $info = MyBlockChain::$bitcoin->gettransaction($txid);
    return $info;
  }

  public static function getRawInfo($txid, $forceUpdate = false)
  {
    self::log("Transaction::getRawInfo $txid");
    if (count(self::$transactions[$txid]) > 0 && $forceUpdate == false)
    {
      return self::$transactions[$txid]['info'];
    } else {
      return MyBlockChain::$bitcoin->getrawtransaction($txid, 1);
    }
  }



  public static function detailsScan($txid)
  {
    self::log("Transaction::detailsScan $txid");
    $info = self::getInfo($txid);
    $transaction_id = self::getID($txid);
    foreach ($info['details'] as $detail)
    {
      $account_id = Account::getID($detail['account']);
      $address_id = Address::getID($detail['address']);
      $amount = floatval($detail['amount']);
      $fee = floatval($detail['fee']);

      $detail_id = MyBlockChain::$db->getval("select detail_id from transactions_details where transaction_id = $transaction_id and address_id = $address_id and amount = $amount");
      if (!$detail_id)
      {
        $detail_id = MyBlockChain::$db->doinsert("insert into transactions_details (transaction_id, account_id, address_id, amount, fee) values ($transaction_id, $account_id, $address_id, $amount, $fee)");
      }
    }
  }

  public function vOutScan($transaction_id, $vouts)
  {
    self::log("Transaction::vOutScan $transaction_id ".json_encode($vouts));
    $voutCount = MyBlockChain::$db->getval("select count(*) from transactions_vouts where transaction_id = $transaction_id");
    $voutFound = count($vouts);
    if ($voutFound > $voutCount)
    {
      foreach ($vouts as $vout)
      {
        $vout_id = vOut::getID($transaction_id, $vout);
      }
    }
  }

  public function vInScan($transaction_id, $vins, $distanceAway = 0)
  {
    self::log("Transaction::vInScan $transaction_id ".json_encode($vins));
    $vinCount = MyBlockChain::$db->getval("select count(*) from transactions_vins where transaction_id = $transaction_id");
    $vinFound = count($vins);
    if ($vinFound > $vinCount)
    {
      foreach ($vins as $vin)
      {
        $vin_id = vIn::getID($transaction_id, $vin, $distanceAway++, ($distanceAway <= self::$maxScanDistance));
      }
    }
  }
}

class TransactionVout extends MyBlockChainRecord {

  var $vout_id;
  var $transaction_id;
  var $value;
  var $n;
  var $asm;
  var $hex;
  var $reqSigs;
  var $type;
  var $txid;

  public static $vouts;

  public function __construct()
  {

  }

  public function getID($transaction_id, $vout)
  {
    self::log("vOut::getId $transaction_id $vout");
    //echo "processing vout\n";
    $voutsID = $transaction_id."-".$vout;
    if (isset(self::$vouts["$voutsID"]) && self::$vouts["$voutsID"]['vout_id'] > 0)
    {
      $vout_id = self::$vouts["$voutsID"]['vout_id'];
    } else {
      $voutIDSQL = "select vout_id from transactions_vouts where transaction_id = $transaction_id and n = ".$vout['n'];
      $vout_id = MyBlockChain::$db->getval($voutIDSQL);
      if (!$vout_id)
      {
        $vosql = "insert into transactions_vouts (transaction_id, txid, value, n, asm, hex, reqSigs, type) values (".$transaction_id.",'".$tx['txid']."','".$vout['value']."','".$vout['n']."','".$vout["scriptPubKey"]['asm']."','".$vout["scriptPubKey"]['hex']."','".$vout["scriptPubKey"]['reqSigs']."','".$vout["scriptPubKey"]['type']."')";
        //echo  "\n\n$vosql\n\n";
        $vout_id = MyBlockChain::$db->doinsert($vosql);

        foreach ($vout["scriptPubKey"]['addresses'] as $address)
        {
          $address_id = Address::getID($address);
          $aisql = "insert into transactions_vouts_addresses (vout_id, address_id) values ($vout_id, $address_id)";
          MyBlockChain::$db->doinsert($aisql);
          $aisql = "insert into addresses_ledger (transaction_id, vout_id, address_id, amount) values ($transaction_id, $vout_id, $address_id, (".$vout['value']."))";
          MyBlockChain::$db->doinsert($aisql);
          MyBlockChainRecord::$addressUpdates[] = $address;
        }
      }
    }

    return $vout_id;
  }

  public function getInfo()
  {

  }


}


class TransactionVin extends MyBlockChainRecord {


  public static $vins;

  public function getID($transaction_id, $vin, $distanceAway = 0, $followtx = true)
  {

    self::log("vIn::getId $transaction_id".json_encode($vin));

    if ($vin['txid'] && isset($vin['vout']))
    {
      $vinsID = $vin['txid']."-".$vin['vout'];
      if (isset(self::$vins[$vinsID]) && $vins[$vinsID]['vin_id'] > 0)
      {
        $vin_id = $vins[$vinsID]['vin_id'];
      } else {
        $vin_id = MyBlockChain::$db->getval("select vin_id from transactions_vins where transaction_id = $transaction_id and txid = '".$vin['txid']."' and vout = ".$vin['vout']);
        if (!$vin_id)
        {
          if ($followtx)
          {
            $vinvout_transaction_id = Transaction::getID($vin['txid'], false, $distanceAway += 1);
            $vin_vout_id = MyBlockChain::$db->getval("select vout_id from transactions_vouts where transaction_id = $vinvout_transaction_id and n = ".$vin['vout']);
          } else {
            $vin_vout_id = null;
          }

          $visql = "insert into transactions_vins (transaction_id, txid, vout, asm, hex, sequence, coinbase, vout_id)  values ('".$transaction_id."','".$vin['txid']."','".$vin['vout']."','".$vin['scriptSig']['asm']."','".$vin['scriptSig']['hex']."','".$vin['sequence']."','".$vin['coinbase']."', '$vin_vout_id')";
          $vin_id = MyBlockChain::$db->doinsert($visql);

          if ($vin_vout_id)
          {
            $vinvoutaddresses = MyBlockChain::$db->gethashrows("select transactions_vouts_addresses.address_id as address_id, transactions_vouts.value as amount from transactions_vouts_addresses inner join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id where transactions_vouts_addresses.vout_id = $vin_vout_id");
            if (count($vinvoutaddresses) > 0)
            {
              foreach ($vinvoutaddresses as $vinaddress)
              {
                  $aisql = "insert into addresses_ledger (transaction_id, vout_id, vin_id, address_id, amount) values ($transaction_id, $vin_vout_id, $vin_id, ".$vinaddress['address_id'].", ".$vinaddress['amount']." * -1)";
                  MyBlockChain::$db->doinsert($aisql);
              }
            }
          }
        }
      }
    } else if ($vin['sequence'] > 0 && !empty($vin['coinbase'])) { // Generation
      $vinsID = $vin['txid']."-".$vin['vout'];
      if (isset(self::$vins[$vinsID]) && $vins[$vinsID]['vin_id'] > 0)
      {
        $vin_id = $vins[$vinsID]['vin_id'];
      } else {
        $vin_id = MyBlockChain::$db->getval("select vin_id from transactions_vins where transaction_id = $transaction_id and txid = '".$vin['txid']."' and sequence = '".$vin['sequence']."' and coinbase = '".$vin['coinbase']."'");

        if (!$vin_id)
        {
          $visql = "insert into transactions_vins (transaction_id, sequence, coinbase, vout_id)  values ('".$transaction_id."','".$vin['sequence']."','".$vin['coinbase']."', 0)";
          $vin_id = MyBlockChain::$db->doinsert($visql);

        }
      }
    } else {
      self::log("can not compute input ".json_encode($vin));
    }



    self::$vins["{$transaction_id}-{$vin}"]["vin_id"] = $vin_id;
    return $vin_id;
  }
}