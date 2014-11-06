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
  * retrieve an Address object
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

  public function getSentSQL($addressSQL, $filterSQL = "")
  {
    $this->trace(__METHOD__);
    //todo: optimize joins / inputs once testing is easier.. seems to work for now
    //todo: add params


    $sentSQL = "select ".
        "distinct receivingVouts.vout_id as vout_id, ".
        "'sent' as type, ".
        "receivingAddresses.address as toAddress, ".
        "sendingAddresses.address as fromAddress, ".
        "receivingVouts.value as value,".
        //"(select time from transactions where transaction_id = receivingVouts.transaction_id) as txtime, ".
        //"(select txid from transactions where transaction_id = receivingVouts.transaction_id) as txid, ".
        //"(select confirmations from transactions where transaction_id = receivingVouts.transaction_id) as confirmations ".

        "transactions.blockindex as blockindex, ".
        "unix_timestamp(transactions.time) as txtime, ".
        "transactions.txid as txid ".


      "from addresses as sendingAddresses ".
        "left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = sendingAddresses.address_id ".
        "left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id ".
        "left join transactions_vins on transactions_vins.vout_id = transactions_vouts.vout_id ".
        "left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id ".
        "left outer join transactions_vouts_addresses as receivingVoutAddresses on receivingVouts.vout_id = receivingVoutAddresses.vout_id ".
        "left outer join addresses as receivingAddresses on receivingVoutAddresses.address_id = receivingAddresses.address_id ".
        "left outer join transactions on receivingVouts.transaction_id = transactions.transaction_id ".
      "where ($addressSQL) ".
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

  public function getSent($sendingAddress, $filters = false)
  {
    $this->trace(__METHOD__);

    $sendingAddress = $this->bc->db->esc($sendingAddress);

    $filterSQL = $this->getFilterSQL($filters);
/*    if ($filters) {
      if (is_numeric($filters->transaction_id)) { //just for a particular transaction
        $filterSQL .= "and (transactions_vouts.transaction_id = {$filters->transaction_id} or receivingVouts.transaction_id = {$filters->transaction_id})";
      }
    }*/

    $sql = $this->getSentSQL("sendingAddresses.address = '$sendingAddress'", $filterSQL);
    return $this->bc->db->assocs($sql);
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

  public function getReceivedSQL($addressSQL, $filterSQL = "")
  {
    $this->trace(__METHOD__);
    //todo: optimize joins / inputs... experiment with 'change' more
    //todo: time in db relates to when the database record was added, fiddle with other options


    $receivedSQL = "select ".
        "transactions_vouts.vout_id as vout_id, ".
        "\"received\" as type, ".
        "receivingAddresses.address as toAddress, ".
        "sendingAddresses(transactions_vouts.transaction_id) as fromAddress, ".
        "transactions_vouts.value as value, ".
        //"(select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime, ".
        //"(select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid, ".
        //"(select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations ".

        "transactions.blockindex as blockindex, ".
        "unix_timestamp(transactions.time) as txtime, ".
        "transactions.txid as txid ".


      "from addresses as receivingAddresses ".
      "left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = receivingAddresses.address_id ".
      "left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id ".
      "left join transactions on transactions_vouts.transaction_id = transactions.transaction_id ".
      "where ".
        "($addressSQL) ".
        "and transactions_vouts.value is not null ".
        $filterSQL;


    $this->trace($receivedSQL);
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

  public function getReceived($receivingAddress, $filters = false)
  {
    $this->trace(__METHOD__);

    $receivingAddress = $this->bc->db->esc($receivingAddress);
/*
    $filterSQL = "";

    if ($filters) {
      if (is_numeric($filters->transaction_id)) { //within a particular transaction
        $filterSQL .= "and (transactions_vouts.transaction_id = {$filters->transaction_id} or transactions_vouts.transaction_id = {$filters->transaction_id})";
      }
    }
*/
    $filterSQL = $this->getFilterSQL($filters);
    $sql = $this->getReceivedSQL("receivingAddresses.address = '$receivingAddress'", $filterSQL);

    return $this->bc->db->assocs($sql);
  }

  /**
  *
  * list of addresses sending and receiving vouts
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
    $this->trace(__METHOD__);

/*    if ($filters) {
      $filters = (object)$filters;
      if (is_numeric($filters->txid)) {
        $filters->transaction_id = $this->bc->transactions->getID($txid);
      }
    }
*/


    $filterSQL = $this->getFilterSQL($filters);


    $this->trace("getting ledger $address $secret_id");

    $receievedSQL = $this->getReceivedSQL($address, $filterSQL);
    $sentSQL = $this->getSentSQL($address, $filterSQL);

    $ledgerSQL = "$receivedSQL union $sentSQL";
    $this->trace($ledgerSQL);
    return $this->bc->db->assocs($ledgerSQL);
  }


  /**
  *
  * nessecary sql to retrieve total received by addresses matching $whereSQL
  *
  *
  * @param string $whereSQL sql used to select addresses to include
  *
  * <code>
  * <?php
  *
  * $receivedSQL = Address::getReceivedTotalSQL("receivingAddress = 'mq7se9wy2egettFxPbmn99cK8v5AFq55Lx'");
  *
  * ?>
  * </code>
   */
  public function getReceivedTotalSQL($addressSQL, $filterSQL)
  {
    $this->trace(__METHOD__);

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
    "where ($addressSQL) ".
      "and sendingVoutsAddresses.address_id != receivingVoutsAddresses.address_id ".
      "$filterSQL ".
      "group by receivingVoutsAddresses.address_id";


      $this->trace($sql);


    return $sql;
  }



  /**
  *
  * total recieved coins
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

  public function getReceivedTotal($receivingAddress, $fiters = false)
  {
    $this->trace(__METHOD__);

    //todo: optimize joins / inputs once testing is easier.. seems to work for now
    //todo: add params (date ranges etc)
    //todo: random thought. if i send arguments as json i can cache based on the hash of the string


    $receivingAddress = $this->bc->db->esc($receivingAddress);
/*
    $filterSQL = "";
    if ($filters) {
      if ($filters['sendingAddress']) { // received from $sendingAddress
        $filterSQL .= " and sendingAddress.address = '".$this->bc->db->esc($sendingAddress)."'";
      }
    }
*/

    $filterSQL = $this->getFilterSQL($filters);

    $sql = $this->getReceivedTotalSQL("receivingAddresses.address = '$receivingAddress' ", $filterSQL);
    $receivedTotal = $this->bc->db->value($sql);

    return $receivedTotal;

  }




  /**
  *
  * nessecary sql to retrieve total sent by addresses matching $whereSQL
  *
  *
  * @param string $whereSQL sql used to select addresses to include
  *
  * <code>
  * <?php
  *
  * $receivedSQL = Address::getSentTotalSQL("sendingAddresses = 'mq7se9wy2egettFxPbmn99cK8v5AFq55Lx'");
  *
  * ?>
  * </code>
   */

  public function getSentTotalSQL($addressSQL, $filterSQL = "")
  {
    $this->trace(__METHOD__);

    $sql = "select  ".
      "sum(receivingVouts.value) ".
    "from addresses as sendingAddresses ".
      "left join transactions_vouts_addresses as sendingVoutsAddresses on sendingVoutsAddresses.address_id = sendingAddresses.address_id ".
      "left join transactions_vouts as sendingVouts on sendingVoutsAddresses.vout_id = sendingVouts.vout_id ".
      "left join transactions_vins on transactions_vins.vout_id = sendingVouts.vout_id ".
      "left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id ".
      "left outer join transactions_vouts_addresses as receivingVoutsAddresses on receivingVoutsAddresses.vout_id = receivingVouts.vout_id ".
      "left outer join addresses as receivingAddresses on receivingVoutsAddresses.address_id = receivingAddresses.address_id ".
    "where ($addressSQL) ".
      "and sendingVoutsAddresses.address_id != receivingVoutsAddresses.address_id ".
      "$filterSQL ".
    "group by receivingVoutsAddresses.address_id";

    $this->trace($sql);
    return $sql;

  }



  /**
  *
  * total sent (spent) coins
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

  public function getSentTotal($sendingAddress, $filters = false)
  {
    $this->trace(__METHOD__);

    //todo: optimize joins / inputs once testing is easier.. seems to work for now
    //todo: add params (date ranges etc)
/*
    $filterSQL = "";
    if ($filters) {
      if ($filters['receivingAddress']) { // sent from $sendingAddress to $receivingAddress
        $filterSQL .= "and receivingAddress.address = ? ";
      }
    }*/

    $filterSQL = $this->getFilterSQL($filters);

    $sql = $this->getSentTotalSQL("sendingAddresses.address = ? ", $filterSQL);
    $sentTotal = $this->bc->db->value($sql, $sqlargs);

    return $sentTotal;
  }




  /**
  *
  * total unsent (spent) coins
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

  public function getUnspentTotalSQL($addressSQL, $filterSQL)
  {
    $this->trace(__METHOD__);

    //todo: benchmark id based
    /*
    $address_id = $this->bc->addresses->getID($address);
    */


    $this->trace("getUnspentTotal sql:");

    $sql = "select  ".
      "sum(vouts.value) ".
    "from addresses ".
      "left join transactions_vouts_addresses as voutsAddresses on voutsAddresses.address_id = addresses.address_id ".
      "left join transactions_vouts as vouts on voutsAddresses.vout_id = vouts.vout_id ".
    "where vouts.spentat is null and ".
    "($addressSQL) $filterSQL";

    return $sql;
  }


  /**
  *
  * total unsent (spent) coins
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

  public function getUnspentTotal($address, $filters = false)
  {
    $this->trace(__METHOD__);

    //todo: benchmark id based
    /*
    $address_id = $this->bc->addresses->getID($address);

    etc etc
    */


    $filterSQL = "";
    //if ($filters) { } //placeholder


    $this->trace("getUnspentTotal sql:");
    $sql = $this->unspentTotalSQL("addresses.address = '$address'", $filterSQL);
    $receivedTotal = $this->bc->db->value($sql);

    return $receivedTotal;
  }


  /*
  *
  * takes any strtotime compatible date string and makes it database insert ready
  * if the setTime argument is passed, sets the time to the 'start' or 'end' of the day
  *
  */

  public function prepDate($dateStr, $setTime = false)
  {

    if (!is_numeric($dateStr)) {
      $dateInt = strtotime($dateStr);
    } else {
      $dateInt = $dateStr;
    }

    if ($dateInt) {

      switch($setTime) {
        case 'end':
          $timeStr = '23:59:59';
        break;

        case 'start':
          $timeStr = '00:00:00';
        break;

        default:
          $timeStr = 'H:i:s';
        break;
      }

      return date("Y-m-d H:i:s", $dateInt);
    } else {
      $this->error('invalid date passed'); //should never happen in production without someone fucking around
    }
  }

  /*
  *
  * assembles any filters passed into sql
  *
  */

  public function getFilterSQL($filters)
  {

    $filterSQL = "";
    if (count($filters) > 0) {

      if ($filters['startDate'] && ($startDate = $this->prepDate($filters['startDate'], 'start'))) {
        $filterSQL .= "and transactions.time >= '$startDate' ";
      }

      if ($filters['endDate'] && ($endDate = $this->prepDate($filters['endDate'], 'end'))) {
        $filterSQL .= "and transactions.time <= '$endDate' ";
      }

      if ($filters['txid']) {
        $filterSQL .= "and transactions.txid = '".$this->db->esc($txid)."' ";
      }

      if (is_numeric($filters['transaction_id'])) { //within a particular transaction
        $filterSQL .= "and (transactions_vouts.transaction_id = ".$filters['transaction_id']." or transactions_vouts.transaction_id = ".$filters['transaction_id']." ";
      }

      if ($filters['receivingAddress']) { // sent from $sendingAddress to $receivingAddress
        $filterSQL .= "and receivingAddress.address = ? ";
      }

      if ($filters['sendingAddress']) { // received from $sendingAddress
        $filterSQL .= "and sendingAddress.address = '".$this->db->esc($filters['sendingAddress'])."' ";
      }

    }

    return $filterSQL;

  }

}