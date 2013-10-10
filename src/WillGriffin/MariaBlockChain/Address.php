<?php

namespace WillGriffin\MariaBlockChain;

require_once "BlockChain.php";

class Address extends BasicObject
{

  //var $address_id;
  var $address;
  var $isvalid;
  var $ismine;
  var $isscript;
  var $pubkey;
  var $iscompressed;
  var $account;

  public function __construct($address = false)
  {
    parent::__construct();
    if ($address)
    {
      $this->address = $address;
      $this->validate();
    }
  }

  /**
  *
  * populates objects properties with returned info and returns validity boolean
  *
  *
  * @param string address related address to fetch the info
  *
  * <code>
  * <?php
  *
  * $address_id = Address::getInfo('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */
  public function validate()
  {
    Main::log("validating address {$this->address}");
    $addrInfo = BlockChain::$bitcoin->validateaddress($this->address);
    if ($addrInfo['isvalid'])
    {
      $this->address = $addrInfo['address'];
      $this->isvalid = $addrInfo['isvalid'];
      $this->ismine = $addrInfo['ismine'];
      $this->isscript = $addrInfo['isscript'];
      $this->pubkey = $addrInfo['pubkey'];
      $this->iscompressed = $addrInfo['iscompressed'];
      return true;
    } else {
      return false;
    }
  }

  /**
  *
  * returns information about an address
  *
  *
  * @param string address related address to fetch the info
  *
  * <code>
  * <?php
  *
  * $address_id = Address::getInfo('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */
  public static  function getInfo($address)
  {
    self::log("Address::getInfo $address");
    $addrInfo = BlockChain::$bitcoin->validateaddress($address);
    return $addrInfo;
  }


  /**
  *
  * retrieves primary key for an address from the db, if non existant adds it
  *
  *
  * @param string address related address to fetch the ledger for
  *
  * <code>
  * <?php
  *
  * $address_id = Address::getID('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */

  public static function getID($address)
  {
    self::log("Address::getID $address");
    $address_id = BlockChain::$db->value("select address_id from addresses where address = '$address'");

    self::log("found address_id $address_id");

    if (!$address_id)
    {
      self::log("no love, creating");
      $info = BlockChain::$bitcoin->validateaddress($address);

      self::log(json_encode($info));
      self::log("address $address ".$info->isvalid);
      if ($info->isvalid)
      {
        self::log("she's valid");

        if ($info->ismine)
        {
          $account = BlockChain::$bitcoin->getaccount($address);
          self::log("getting account id");
          $account_id = Account::getID($account);
        } else {
          $account_id = 0;
        }

        self::log("this far");

        //echo "account_id: $account_id\n";
        $address_id = BlockChain::$db->insert("insert into addresses (account_id, address, pubkey, ismine, isscript, iscompressed) values ($account_id, '$address', '".$info->pubkey."',".intval($info->ismine).",".intval($info->isscript).",".intval($info->iscompressed).")");

        self::log("farther");


      } else {
        self::log("invalid address");
        echo "invalid address";
        die;
      }

    }
    self::log("address_id $address_id");

    return $address_id;
  }




  /**
  *
  * An attempt to retrieve a ledger for a bitcoin address, way too slow and i'm not sure it works
  *
  *
  * @param string address related address to fetch the ledger for
  *
  * <code>
  * <?php
  *
  * $ledger = Address::slowGetLedger('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */

  //todo: here for reference, delete in cleanup
  public static function slowGetLedger($address, $since = 0)
  {
    self::log("Address::slowGetLedger $address $since");
    $ledgerSQL = "
      (select concat(vins.vin_id,'-',vouts.vout_id,'-',voutAddresses.address_id) as rid, (vouts.value * -1) as amount, voutAddress.address as description, txs.time as txtime, txs.txid as txid, txs.confirmations as confirmations
      from addresses as voutAddress
        inner join transactions_vouts_addresses as voutAddresses on voutAddresses.address_id = voutAddress.address_id
        inner join transactions_vouts as vouts on vouts.vout_id = voutAddresses.vout_id
        inner join transactions as txs on txs.transaction_id = vouts.transaction_id
        inner join transactions_vins as vins on vins.transaction_id = txs.transaction_id
        inner join transactions_vouts as vinsVouts on vinsVouts.vout_id = vins.vout_id
        inner join transactions_vouts_addresses as vinsAddresses on vinsAddresses.vout_id = vinsVouts.vout_id
        inner join addresses as vinAddress on vinAddress.address_id = vinsAddresses.address_id
      where
        vinAddress.address = '$address')
      union
      (select concat(vins.vin_id,'-',vinsVouts.vout_id,'-',voutAddresses.address_id) as rid, vouts.value as amount, vinAddress.address as description,  txs.time as txtime, txs.txid as txid, txs.confirmations as confirmations
      from addresses as voutAddress
        inner join transactions_vouts_addresses as voutAddresses on voutAddresses.address_id = voutAddress.address_id
        inner join transactions_vouts as vouts on vouts.vout_id = voutAddresses.vout_id
        inner join transactions as txs on txs.transaction_id = vouts.transaction_id
        inner join transactions_vins as vins on vins.transaction_id = txs.transaction_id
        inner join transactions_vouts as vinsVouts on vinsVouts.vout_id = vins.vout_id
        inner join transactions_vouts_addresses as vinsAddresses on vinsAddresses.vout_id = vinsVouts.vout_id
        inner join addresses as vinAddress on vinAddress.address_id = vinsAddresses.address_id
      where
        voutAddress.address = '$address')
        order by txtime asc";

    return BlockChain::$db->assocs($ledgerSQL);
  }


  /**
  *
  * sql to retrieve list of address credits (sent coins)
  *
  *
  * @param string address related address to fetch sent ledger sql for
  *
  * <code>
  * <?php
  *
  * $receivedSQL = Address::getSentSQL('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx');
  *
  * ?>
  * </code>
   */

  public static function getSentSQL($address, $secret_id = null)
  {
    //todo: optimize joins / inputs once testing is easier.. seems to work for now
    //todo: add params


    /*return "select
      \"sent\" as type,
      receivingAddresses.address as address,
      (select label from addresses_labels where addresses_labels.address_id = receivingAddresses.address_id) as label,
      receivingVouts.value as value,
      (select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime,
      (select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid,
      (select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations,
      receivingVouts.vout_id as vout_id
      from addresses as targetAddresses
      left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id
      left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id
      left join transactions_vins on transactions_vins.vout_id = transactions_vouts.vout_id
      left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id
      left outer join transactions_vouts_addresses as receivingVoutAddresses on receivingVouts.vout_id = receivingVoutAddresses.vout_id
      left outer join addresses as receivingAddresses on receivingVoutAddresses.address_id = receivingAddresses.address_id
      where targetAddresses.address = \"".BlockChain::$db->esc($address)."\""; */

      return "select
        \"sent\" as type,
        receivingAddresses.address as toAddress,
        targetAddresses.address as fromAddress,
        (select label from addresses_labels where addresses_labels.address_id = targetAddresses.address_id) as toLabel,
        (select label from addresses_labels where addresses_labels.address_id = receivingAddresses.address_id) as fromLabel,
        receivingVouts.value as value,
        (select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime,
        (select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid,
        (select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations,
        receivingVouts.vout_id as vout_id
      from addresses as targetAddresses
        left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id
        left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id
        left join transactions_vins on transactions_vins.vout_id = transactions_vouts.vout_id
        left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id
        left outer join transactions_vouts_addresses as receivingVoutAddresses on receivingVouts.vout_id = receivingVoutAddresses.vout_id
        left outer join addresses as receivingAddresses on receivingVoutAddresses.address_id = receivingAddresses.address_id
      where (targetAddresses.address = \"".BlockChain::$db->esc($address)."\") or

        targetAddresses.address_id in (
          select alias_address_id
          from addresses_aliases
          inner join addresses as aliasAddresses on addresses_aliases.alias_address_id = aliasAddresses.address_id
          where
            aliasAddresses.address = \"".BlockChain::$db->esc($address)."\")
      and receivingVouts.value is not null";





  }





  /**
  *
  * list of address credits (sent coins)
  *
  *
  * @param string address related address to fetch the ledger for
  *
  * <code>
  * <?php
  *
  * $addressOutputs = Address::getSent('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */

  public static function getSent($address, $secret_id = null)
  {
      //echo json_encode(BlockChain::$db->assocs($sentSQL));
      return BlockChain::$db->assocs(self::getSentSQL($address));
  }


  /**
  *
  * sql to retrieve list of address debits (received coins)
  *
  *
  * @param string address related address to fetch received ledger sql for
  *
  * <code>
  * <?php
  *
  * $receivedSQL = Address::getReceivedSQL('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */

  public static function getReceivedSQL($address, $secret_id = null)
  {
    //self::getInputs($address);
    //todo: optimize joins / inputs... experiment with 'change' more
    //todo: time in db relates to when the database record was added, fiddle with other options
    //todo: consider adding support for aliases
    /*return "select
      \"received\" as type,
      sendingAddresses.address as address,
      (select label from addresses_labels where addresses_labels.address_id = sendingAddresses.address_id) as label,
      transactions_vouts.value as value,
      (select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime,
      (select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid,
      (select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations,
      transactions_vouts.vout_id as vout_id
      from addresses as targetAddresses
      left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id
      left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id
      left outer join transactions_vins on transactions_vins.transaction_id = transactions_vouts.transaction_id
      left outer join transactions_vouts as sendingVouts on transactions_vins.transaction_id = sendingVouts.transaction_id
      left outer join transactions_vouts_addresses as sendingVoutAddresses on sendingVouts.vout_id = sendingVoutAddresses.vout_id
      left outer join addresses as sendingAddresses on sendingVoutAddresses.address_id = sendingAddresses.address_id
      where targetAddresses.address = \"".BlockChain::$db->esc($address)."\" and targetAddresses.address_id != sendingAddresses.address_id"; */

      return "select
        \"received\" as type,
        targetAddresses.address as toAddress,
        sendingAddresses.address as fromAddress,
        (select label from addresses_labels where addresses_labels.address_id = targetAddresses.address_id) as toLabel,
        (select label from addresses_labels where addresses_labels.address_id = sendingAddresses.address_id) as fromLabel,
        transactions_vouts.value as value,
        (select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime,
        (select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid,
        (select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations,
        transactions_vouts.vout_id as vout_id
      from addresses as targetAddresses
        left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id
        left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id
        left outer join transactions_vins on transactions_vins.transaction_id = transactions_vouts.transaction_id
        left outer join transactions_vouts as sendingVouts on transactions_vins.transaction_id = sendingVouts.transaction_id
        left outer join transactions_vouts_addresses as sendingVoutAddresses on sendingVouts.vout_id = sendingVoutAddresses.vout_id
        left outer join addresses as sendingAddresses on sendingVoutAddresses.address_id = sendingAddresses.address_id
      where (targetAddresses.address = \"".BlockChain::$db->esc($address)."\" and targetAddresses.address_id != sendingAddresses.address_id) or
        targetAddresses.address_id in (
          select alias_address_id
          from addresses_aliases
          inner join addresses as aliasAddresses on addresses_aliases.alias_address_id = aliasAddresses.address_id
          where
            aliasAddresses.address = \"".BlockChain::$db->esc($address)."\" and aliasAddresses.address_id != sendingAddresses.address_id)
      and transactions_vouts.value is not null";


  }


  /**
  *
  * list of address debits (received coins)
  *
  *
  * @param string address related address to fetch the ledger for
  *
  * <code>
  * <?php
  *
  * $addressOutputs = Address::getSent('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */

  public static function getReceived($address, $secret_id = null)
  {
    return BlockChain::$db->assocs(self::getReceivedSQL($address));
  }


  /**
  *
  * list of address debits (received coins)
  *
  *
  * @param string address related address to fetch the ledger for
  *
  * <code>
  * <?php
  *
  * $addressOutputs = Address::getSent('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */

  public static function getLedger($address, $secret_id = null)
  {

    self::log("getting ledger $address $secret_id");
    $ledgerSQL = self::getReceivedSQL($address, $secret_id)." union ".self::getSentSQL($address, $secret_id);
    self::log($ledgerSQL);
    //echo $ledgerSQL;
    return BlockChain::$db->assocs($ledgerSQL);
  }
}



//dirty burger