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
    $addrInfo = $this->bc->rpc->validateaddress($this->address);
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
    $addrInfo = $this->bc->rpc->validateaddress($address);
    $this->trace($addrInfo);

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
    $address_id = $this->bc->db->value("select address_id ".
      "from addresses ".
      "where address = ?",
      ['s', $address]);

    $this->trace("found address_id ($address_id)");

    if (!$address_id) {
      $this->trace("no love, creating ");
      $info = $this->bc->rpc->validateaddress("$address");

      $this->trace($info);
      if ($info->isvalid) {

        if ($info->ismine) {
          $account = $this->bc->rpc->getaccount($address);
          $this->trace("getting account id");
          $account_id = $this->bc->accounts->getID($account);
        } else {
          $account_id = 0;
        }

        $address_id = $this->bc->db->insert("insert into addresses ".
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
        "distinct receivingVouts.vout_id as vout_id, ".
        "'sent' as type, ".
        "receivingAddresses.address as toAddress, ".
        "targetAddresses.address as fromAddress, ".
        "receivingVouts.value as value,".
        "(select time from transactions where transaction_id = receivingVouts.transaction_id) as txtime, ".
        "(select txid from transactions where transaction_id = receivingVouts.transaction_id) as txid, ".
        "(select confirmations from transactions where transaction_id = receivingVouts.transaction_id) as confirmations ".
      "from addresses as targetAddresses ".
        "left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id ".
        "left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id ".
        "left join transactions_vins on transactions_vins.vout_id = transactions_vouts.vout_id ".
        "left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id ".
        "left outer join transactions_vouts_addresses as receivingVoutAddresses on receivingVouts.vout_id = receivingVoutAddresses.vout_id ".
        "left outer join addresses as receivingAddresses on receivingVoutAddresses.address_id = receivingAddresses.address_id ".
      "where ((targetAddresses.address = \"".$this->bc->db->esc($address)."\") ".

      "or targetAddresses.address_id in ( ".
          "select alias_address_id ".
          "from addresses_aliases ".
          "inner join addresses as aliasAddresses on addresses_aliases.address_id = aliasAddresses.address_id ".
          "where ".
            "aliasAddresses.address = \"".$this->bc->db->esc($address)."\")) ".

      "and receivingVouts.value is not null ".
      "$filterSQL group by vout_id";

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
    //echo json_encode($this->bc->db->assocs($sentSQL));
    return $this->bc->db->assocs($this->getSentSQL($address));
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
  *
  * ?>
  * </code>
  *
  * Depends on the database containing the following stored function
  *
  * drop function if exists sendingAddresses;
  * delimiter  ~
  *
  * create function sendingAddresses (transactionID int)
  *   returns text reads sql data
  *   begin
  *     declare fini integer default 0;
  *     declare address varchar(100) default "";
  *     declare addresses text default "";
  *
  *     declare transaction_vin_addresses cursor for
  *       select
  *         distinct addresses.address
  *       from addresses
  *       left join transactions_vouts_addresses on addresses.address_id = transactions_vouts_addresses.address_id
  *       left join transactions_vins on transactions_vouts_addresses.vout_id = transactions_vins.vout_id
  *       where transactions_vins.transaction_id = transactionID;
  *
  *     declare continue handler
  *         for not found set fini = 1;
  *
  *     open transaction_vin_addresses;
  *
  *     address_loop: loop
  *
  *       fetch transaction_vin_addresses into address;
  *       if fini = 1 then
  *           leave address_loop;
  *       end if;
  *
  *       set addresses = concat(address,',',addresses);
  *     end loop address_loop;
  *
  *     close transaction_vin_addresses;
  *     return substring(addresses,1,length(addresses)-1);
  *   end ~
  *
  * delimiter ;
  *
  */

  public function getReceivedSQL($address, $filters = false)
  {
    //todo: optimize joins / inputs... experiment with 'change' more
    //todo: time in db relates to when the database record was added, fiddle with other options

    $filterSQL = "";

    if ($filters && is_numeric($filters->transaction_id)) {
      $filterSQL .= "and (transactions_vouts.transaction_id = {$filters->transaction_id} or transactions_vouts.transaction_id = {$filters->transaction_id})";
    }

    $receivedSQL = "select ".
        "transactions_vouts.vout_id as vout_id, ".
        "\"received\" as type, ".
        "targetAddresses.address as toAddress, ".
        "sendingAddresses(transactions_vouts.transaction_id) as fromAddress, ".
        "transactions_vouts.value as value, ".
        "(select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime, ".
        "(select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid, ".
        "(select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations ".

      "from addresses as targetAddresses ".
      "left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id ".
      "left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id ".
      "where ".
      "((targetAddresses.address = \"".$this->bc->db->esc($address)."\") or targetAddresses.address_id in ( select alias_address_id from addresses_aliases inner join addresses as aliasAddresses on addresses_aliases.address_id = aliasAddresses.address_id where aliasAddresses.address = \"".$this->bc->db->esc($address)."\")) ".
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
    return $this->bc->db->assocs($this->getReceivedSQL($address));
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

    $receivedTotal = $this->bc->db->value($sql, $sqlargs);

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
        $filters->transaction_id = $this->bc->transactions->getID($txid);
      }
    }

    $this->trace("getting ledger $address $secret_id");
    $ledgerSQL = $this->getReceivedSQL($address, $filters)." union ".$this->getSentSQL($address, $filters);
    $this->trace($ledgerSQL);
    return $this->bc->db->assocs($ledgerSQL);
  }


  public function get($address)
  {
    $this->trace( __METHOD__." ".$address );
    $this->trace( get_class( $this->bc ) );

    if ($address) {
      $cached = $this->bc->cache->get($address);
      if ($cached !== false) {
        $this->trace( "getting from cache" );
        $address = new Address($this->bc, $cached);
      } else {
        $this->trace( "getting address" );
        $address = new Address($this->bc, $address);
        $this->bc->cache->set( "$address", $address->toArray(), false, 60 );
      }
      return $address;
    }
  }

}