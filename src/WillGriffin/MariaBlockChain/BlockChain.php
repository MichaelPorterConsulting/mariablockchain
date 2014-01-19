<?php

namespace WillGriffin\MariaBlockChain;

require_once "Account.php";
require_once "Address.php";
require_once "Transaction.php";

class BasicObject
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
          //var_dump($hook);
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
          error_log($msg);
        }
      }

    }
  }

}

class BlockChain extends BasicObject
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
    $id = BlockChain::$db->value($sql);
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
      $id = BlockChain::$db->insert($isql);
    }
    return $id;
  }*/

  public function clearDatabase()
  {
    //echo "Clearing out database\n";
    BlockChain::$db->update("delete from transactions");
    BlockChain::$db->update("delete from transactions_vouts");
    BlockChain::$db->update("delete from transactions_vouts_addresses");
    BlockChain::$db->update("delete from transactions_vins");
    BlockChain::$db->update("delete from addresses");
    BlockChain::$db->update("delete from accounts");
    BlockChain::$db->update("delete from transactions_details");
  }

  /*
  *
  * looks for new transactions, updates database if found
  * todo: move this
  * */

  public static function scan()
  {
    self::log("BlockChain::scan");
    $timenow = time();
    //todo: memcache
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
      $lastScannedBlock = BlockChain::$db->value("select blockhash from transactions where blockhash is not null and time != '0000-00-00 00:00:00' order by time desc limit 0, 1");
      if (!$lastScannedBlock)
      {
        $lastScannedBlock = "";
      }
      self::$lastScannedBlock = $lastScannedBlock;
    }
    //BlockChain::log("Last scanned block ".self::$lastScannedBlock);

    $newtxs = BlockChain::$bitcoin->listsinceblock(self::$lastScannedBlock);




    if (count($newtxs->transactions) > 0 && ($newtxs->lastblock != self::$lastScannedBlock || count($newtxs->transactions) > self::$lastScannedCount))
    {
      BlockChain::log(self::$lastScannedCount - count($newtxs)." new transactions found\n");
      foreach ($newtxs->transactions as $newtx)
      {
        $transaction_id = Transaction::getID($newtx->txid, true, 0);
      }
    }
    self::$lastScannedBlock = $newtxs->lastblock;
    self::$lastScannedCount = count($newtxs->transactions);
    self::broadcastUpdates();
  }

  //todo: anything directly relating to websockets needs to be moved
  public static function broadcastUpdates()
  {
    /*
    if (BlockChain::$addressUpdates)
      BlockChain::$addressUpdates = array_unique(BlockChain::$addressUpdates);

    if (count(BlockChain::$addressUpdates) > 0)
      for ($x = 0; $x < count(BlockChain::$addressUpdates); $x++)
      {
        $address = array_shift(BlockChain::$addressUpdates);

        if (!BlockChain::$addressLastUpdated[$address])
          BlockChain::$addressLastUpdated[$address] = "2012-12-21 21:12:21";

        $ledgerItems = Address::getLedger($address, BlockChain::$addressLastUpdated[$address]);
        foreach ($ledgerItems as $ledgerItem)
        {
          BlockChain::broadcast(json_encode($ledgerItem), $address);
        }
        BlockChain::$addressLastUpdated[$address] = date("Y-m-d H:i:s");
      }*/
  }

}

class BlockChainObject extends BasicObject
{

  var $onUpdate;
  var $onError;

  var $btcd;
  var $db;

  public function __construct($args = "")
  {

    if (!isset($args['onError']))
    {
      if (is_callable(BlockChain::$onError))
      {
        $this->onError = BlockChain::$onError;
      }
    }

    /* setup bitcoind rpc connection */
    if (empty($args['btcd']))
    { //if nothing sent use default if connected
      if (is_object(BlockChain::$bitcoin))
      {
        $this->btcd = BlockChain::$bitcoin;
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
      if (is_object(BlockChain::$db))
      {
        $this->db = BlockChain::$db;
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

  private function _updated($msg)
  {
    if (is_callable($this->onUpdate))
    {
      $this->onUpdate($msg);
    }
  }

}




//kittyland love center