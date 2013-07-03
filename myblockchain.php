<?php

require_once "account.php";
require_once "address.php";
require_once "transaction.php";


class MyBaseObj
{
  static public $hooks;


  public static function addHook($event, $func)
  {
    self::$hooks[$event][] = $func;
  }

  public static function log($msg)
  {
    if (isset(self::$hooks['log']) && count(self::$hooks['log']) > 0)
    {
      foreach (self::$hooks['log'] as $hook)
      {
        if (is_callable($hook))
        {
          $hook($msg);
        } else {
          var_dump($hook);
        }
      }

    }
  }

  public static function error($msg)
  {
    if (isset(self::$hooks['error']) && count(self::$hooks['error']) > 0)
    {
      foreach (self::$hooks['error'] as $hook)
      {
        if (is_callable($hook))
        {
          $hook($msg);
        } else {
          var_dump($hook);
        }
      }

    }
  }

}

//todo: wallet?
class MyBlockChain extends MyBaseObj
{

  static public $bitcoin;
  static public $db;

  //todo: memcache
  static public $lastScannedBlock;
  static public $lastScannedCount;

  static public $addressUpdates;
  static public $addressLastUpdated;

  public function __construct($args)
  {



  }
  /*
  public function getID($filters, $pkfld, $table, $doInsert = true)
  {

    foreach ($filters as $filter => $fval)
      $filtersql .= "$filter = '$fval',";

    $filtersql = rtrim($filtersql, ',');

    $sql = 'select $pkfld from $table $filtersql';
    $id = MyBlockChain::$db->getval($sql);
    if (!$id && $doInsert)
    {
      foreach ($filters as $filter => $fval)
      {
        $iflds .= "$filter,";
        $ivals .= "'$fval'";
      }
      $iflds = rtrim($iflds,',');
      $ivals = rtrim($ivals,',');

      $isql = "insert into $table ($iflds) values ($ivals)";
      $id = MyBlockChain::$db->doinsert($isql);
    }
    return $id;
  }*/

  public function clearDatabase()
  {
    //echo "Clearing out database\n";
    MyBlockChain::$db->doupdate("delete from transactions");
    MyBlockChain::$db->doupdate("delete from transactions_vouts");
    MyBlockChain::$db->doupdate("delete from transactions_vouts_addresses");
    MyBlockChain::$db->doupdate("delete from transactions_vins");
    MyBlockChain::$db->doupdate("delete from addresses");
    MyBlockChain::$db->doupdate("delete from accounts");
    MyBlockChain::$db->doupdate("delete from transactions_details");
  }

  /*
  *
  * looks for new transactions, updates database if found
  * todo: move this
  * */

  public static function scan()
  {

    $timenow = time();
    if (count(Transaction::$transactions) > 0)
    {
      foreach (Transaction::$transactions as $cached_txid => $cached_tx)
      {
        $doUpdate = false;
        if ($cached_tx['confirmations'] < 1 && $cached_tx['lastScanned'] < $timenow - 10)
        {
          $doUpdate = true;
        } else if ($cached_tx['confirmations'] < 3 && $cached_tx['lastScanned'] < $timenow - 60) {
          $doUpdate = true;
        }

        if ($doUpdate)
        {
          echo "forcing update $cached_txid for not enough confirmations\n";
          Transaction::getID($cached_txid, 0, true, true);
        }
      }
    }

    if (empty(self::$lastScannedBlock))
    {
      $lastScannedBlock = MyBlockChain::$db->getval("select blockhash from transactions where blockhash is not null and time != '0000-00-00 00:00:00' order by time desc limit 0, 1");
      if (!$lastScannedBlock)
      {
        $lastScannedBlock = "";
      }
      self::$lastScannedBlock = $lastScannedBlock;
    }
    //MyBlockChain::log("Last scanned block ".self::$lastScannedBlock);

    $newtxs = MyBlockChain::$bitcoin->listsinceblock(self::$lastScannedBlock);

    if (count($newtxs['transactions']) > 0 && ($newtxs['lastblock'] != self::$lastScannedBlock || count($newtxs['transactions']) > self::$lastScannedCount))
    {
      MyBlockChain::log(self::$lastScannedCount - count($newtxs)." new transactions found\n");
      foreach ($newtxs['transactions'] as $newtx)
      {
        $transaction_id = Transaction::getID($newtx['txid'], true, 0);
      }
    }
    self::$lastScannedBlock = $newtxs['lastblock'];
    self::$lastScannedCount = count($newtxs['transactions']);
    self::broadcastUpdates();
  }

  //todo: anything directly relating to websockets needs to be moved
  public static function broadcastUpdates()
  {
    /*
    if (MyBlockChain::$addressUpdates)
      MyBlockChain::$addressUpdates = array_unique(MyBlockChain::$addressUpdates);

    if (count(MyBlockChain::$addressUpdates) > 0)
      for ($x = 0; $x < count(MyBlockChain::$addressUpdates); $x++)
      {
        $address = array_shift(MyBlockChain::$addressUpdates);

        if (!MyBlockChain::$addressLastUpdated[$address])
          MyBlockChain::$addressLastUpdated[$address] = "2012-12-21 21:12:21";

        $ledgerItems = Address::getLedger($address, MyBlockChain::$addressLastUpdated[$address]);
        foreach ($ledgerItems as $ledgerItem)
        {
          MyBlockChain::broadcast(json_encode($ledgerItem), $address);
        }
        MyBlockChain::$addressLastUpdated[$address] = date("Y-m-d H:i:s");
      }*/
  }



}

class MyBlockChainRecord extends MyBaseObj
{

  var $onUpdate;
  var $onError;

  var $btcd;
  var $db;

  public function __construct($args = "")
  {

    if (!isset($args['onError']))
    {
      if (is_callable(MyBlockChain::$onError))
      {
        $this->onError = MyBlockChain::$onError;
      }
    }

    /* setup bitcoind rpc connection */
    if (empty($args['btcd']))
    { //if nothing sent use default if connected
      if (is_object(MyBlockChain::$bitcoin))
      {
        $this->btcd = MyBlockChain::$bitcoin;
      }
    } else if (!is_object($args['btcd']))
    {
      if (is_array($args['btcd']))
      { //todo: accept associate array


      } else if (is_string($args['btcd']))
      { //todo: accept url

      }
    }

    /* setup database connection */
    if (empty($args['db']))
    {
      if (is_object(MyBlockChain::$db))
      {
        $this->db = MyBlockChain::$db;
      } else {
        $this->error("no db connection");
      }
    } else if (!is_object($args['db']))
    {
      if (is_array($args['db']))
      { //todo: accept associate array


      } else if (is_string($args['db']))
      { //todo: accept url

      }
    } else {
      $this->error("no db connection");
    }
  }

  private function updated($msg)
  {
    if (is_callable($this->onUpdate))
    {
      $this->onUpdate($msg);
    }
  }

}




