<?php

namespace WillGriffin\MariaBlockChain;

require_once "Address.php";

class AddressesController extends Object {

  public function __construct($blockchain) {

    parent::__construct($blockchain);

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
  public function validate($address)
  {
    $this->trace("validating address $address");
    $addrInfo = $this->blockchain->rpc->validateaddress($this->address);
    if ($addrInfo->isvalid)
    {
      $this->address = $addrInfo->address;
      $this->isvalid = $addrInfo->isvalid;
      $this->ismine = $addrInfo->ismine;
      $this->isscript = $addrInfo->isscript;
      $this->pubkey = $addrInfo->pubkey;
      $this->iscompressed = $addrInfo->iscompressed;
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
  public function getInfo($address)
  {
    $this->trace("Address::getInfo $address");
    $addrInfo = $this->blockchain->rpc->validateaddress($address);
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

  public function getID($address)
  {
    $this->trace("Address::getID $address");
    $address_id = $this->blockchain->db->value("select address_id ".
      "from addresses ".
      "where address = ?",
      ['s', $address]);

    $this->trace("found address_id ($address_id)");

    if (!$address_id) {
      $this->trace("no love, creating ");
      $info = $this->blockchain->rpc->validateaddress("$address");

      $this->trace($info);
      if ($info->isvalid) {

        if ($info->ismine) {
          $account = $this->blockchain->rpc->getaccount($address);
          $this->trace("getting account id");
          $account_id = $this->blockchain->accounts->getID($account);
        } else {
          $account_id = 0;
        }

        $address_id = $this->blockchain->db->insert("insert into addresses ".
          "(account_id, ".
            "address, ".
            "pubkey, ".
            "ismine, ".
            "isscript, ".
            "iscompressed".
          ") values (?, ?, ?, ?, ?, ?)",
          ['issiii',
            $account_id,
            $address,
            $info->pubkey,
            intval($info->ismine),
            intval($info->isscript),
            intval($info->iscompressed)]);

      } else {
        $this->trace("invalid address");
        echo "invalid address";
        die;
      }

    }
    $this->trace("address_id $address_id");

    return $address_id;
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

  public function getSentSQL($address, $filters = false)
  {
    //todo: optimize joins / inputs once testing is easier.. seems to work for now
    //todo: add params

    $filterSQL = "";

    if ($filters && is_numeric($filters->transaction_id)) {
      $filterSQL .= "and (transactions_vouts.transaction_id = {$filters->transaction_id} or receivingVouts.transaction_id = {$filters->transaction_id})";
    }

    $sentSQL = "select ".
        "'sent' as type, ".
        "receivingAddresses.address as toAddress, ".
        "targetAddresses.address as fromAddress, ".
        //"(select label from addresses_labels where addresses_labels.address_id = targetAddresses.address_id) as toLabel,".
        //"(select label from addresses_labels where addresses_labels.address_id = receivingAddresses.address_id) as fromLabel,".
        "receivingVouts.value as value,".
        "(select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime, ".
        "(select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid, ".
        "(select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations, ".
        "receivingVouts.vout_id as vout_id ".
      "from addresses as targetAddresses ".
        "left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id ".
        "left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id ".
        "left join transactions_vins on transactions_vins.vout_id = transactions_vouts.vout_id ".
        "left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id ".
        "left outer join transactions_vouts_addresses as receivingVoutAddresses on receivingVouts.vout_id = receivingVoutAddresses.vout_id ".
        "left outer join addresses as receivingAddresses on receivingVoutAddresses.address_id = receivingAddresses.address_id ".
      "where ((targetAddresses.address = \"".$this->blockchain->db->esc($address)."\") ".

      "or targetAddresses.address_id in ( ".
          "select alias_address_id ".
          "from addresses_aliases ".
          "inner join addresses as aliasAddresses on addresses_aliases.alias_address_id = aliasAddresses.address_id ".
          "where ".
            "aliasAddresses.address = \"".$this->blockchain->db->esc($address)."\")) ".

      "and receivingVouts.value is not null ".
      $filterSQL;

      return $sentSQL;
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

  public function getSent($address)
  {
    //echo json_encode($this->blockchain->db->assocs($sentSQL));
    return $this->blockchain->db->assocs($this->getSentSQL($address));
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

  public function getReceivedSQL($address, $filters = false)
  {
    //todo: optimize joins / inputs... experiment with 'change' more
    //todo: time in db relates to when the database record was added, fiddle with other options

    $filterSQL = "";

    if ($filters && is_numeric($filters->transaction_id)) {
      $filterSQL .= "and (transactions_vouts.transaction_id = {$filters->transaction_id} or sendingVouts.transaction_id = {$filters->transaction_id})";
    }


    $receivedSQL = "select ".
      "\"received\" as type, ".
      "targetAddresses.address as toAddress, ".
      "sendingAddresses.address as fromAddress, ".
      //"(select label from addresses_labels where addresses_labels.address_id = targetAddresses.address_id) as toLabel, ".
      //"(select label from addresses_labels where addresses_labels.address_id = sendingAddresses.address_id) as fromLabel, ".
      "transactions_vouts.value as value, ".
      "(select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime, ".
      "(select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid, ".
      "(select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations, ".
      "transactions_vouts.vout_id as vout_id ".
    "from addresses as targetAddresses ".
      "left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id ".
      "left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id ".
      "left outer join transactions_vins on transactions_vins.transaction_id = transactions_vouts.transaction_id ".
      "left outer join transactions_vouts as sendingVouts on transactions_vins.transaction_id = sendingVouts.transaction_id ".
      "left outer join transactions_vouts_addresses as sendingVoutAddresses on sendingVouts.vout_id = sendingVoutAddresses.vout_id ".
      "left outer join addresses as sendingAddresses on sendingVoutAddresses.address_id = sendingAddresses.address_id ".
    "where ((targetAddresses.address = \"".$this->blockchain->db->esc($address)."\" and targetAddresses.address_id != sendingAddresses.address_id) or ".
      "targetAddresses.address_id in ( ".
        "select alias_address_id ".
        "from addresses_aliases ".
        "inner join addresses as aliasAddresses on addresses_aliases.alias_address_id = aliasAddresses.address_id ".
        "where ".
          "aliasAddresses.address = \"".$this->blockchain->db->esc($address)."\" and aliasAddresses.address_id != sendingAddresses.address_id)) ".
    "and transactions_vouts.value is not null ".
    $filterSQL;
    return $receivedSQL;

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

  public function getReceived($address)
  {
    return $this->blockchain->db->assocs($this->getReceivedSQL($address));
  }




  /**
  *
  * total of sent by address
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

  public function getReceivedTotal($receivingAddress, $sendingAddress = false)
  {
    //todo: optimize joins / inputs once testing is easier.. seems to work for now
    //todo: add params (date ranges etc)

    if ($sendingAddress) {
      $sqlargs = ['ss', $receivingAddress, $sendingAddress];
      $sendingWhereClause = "and sendingAddresses.address = ? ";
    } else {
      $sqlargs = ['s', $receivingAddress];
      $sendingWhereClause = "";
    }

    $sql = "select  ".
      "sum(receivingVouts.value), ".
      "sendingAddresses.address ".
    "from addresses as sendingAddresses ".
      "left join transactions_vouts_addresses as sendingVoutsAddresses on sendingVoutsAddresses.address_id = sendingAddresses.address_id ".
      "left join transactions_vouts as sendingVouts on sendingVoutsAddresses.vout_id = sendingVouts.vout_id ".
      "left join transactions_vins on transactions_vins.vout_id = sendingVouts.vout_id ".
      "left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id ".
      "left outer join transactions_vouts_addresses as receivingVoutsAddresses on receivingVoutsAddresses.vout_id = receivingVouts.vout_id ".
      "left outer join addresses as receivingAddresses on receivingVoutsAddresses.address_id = receivingAddresses.address_id ".
    "where receivingAddresses.address = ? ".$sendingWhereClause.
    "and sendingVoutsAddresses.address_id != receivingVoutsAddresses.address_id ".
    "group by receivingVoutsAddresses.address_id";

    $receivedTotal = $this->blockchain->db->value($sql, $sqlargs);

    return $receivedTotal;
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

  public function getLedger($address, $filters = false)
  {
    if ($filters) {
      $filters = (object)$filters;
      if (is_numeric($filters->txid)) {
        $filters->transaction_id = $this->blockchain->transactions->getID($txid);
      }
    }

    $this->trace("getting ledger $address $secret_id");
    $ledgerSQL = $this->getReceivedSQL($address, $filters)." union ".$this->getSentSQL($address, $filters);
    $this->trace($ledgerSQL);
    return $this->blockchain->db->assocs($ledgerSQL);
  }


  public function get($address)
  {

    $cached = $this->blockchain->cache->get($address);
    if ($cached !== false) {
      return $this->blockchain->cache->get("addr:$address");
    } else {
      $address = new Address($this->blockchain, $address);
      $this->blockchain->cache->set( "addr:$address", $address->toArray(), false, 60 );
      return $address;
    }
  }

}