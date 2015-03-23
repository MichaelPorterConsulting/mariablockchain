<?php

namespace willgriffin\MariaBlockChain;

require_once "Address.php";

class AddressesController extends Object
{
  protected $_cachePrefix;
  /**
  *
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
   */
  public function __construct($blockchain)
  {

    if (!isset($this->_cachePrefix))
      $this->_cachePrefix = "";

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
    //$this->trace(__METHOD__." $address");

    $addrInfo = $this->bc->rpc->validateaddress($this->address);
    if ($addrInfo->isvalid) {
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
  *
  */
  public function getInfo($address)
  {
    //$this->trace(__METHOD__." $address");

    $addrInfo = $this->bc->rpc->validateaddress($address);
    //$this->trace($addrInfo);

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
  * $address_id = Address::getId('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */

  public function getAddressId($address)
  {
    //$this->trace(__METHOD__." $address");

    $address_id = $this->bc->db->value("select address_id ".
      "from addresses ".
      "where address = ?",
      ['s', $address]);

    if (!$address_id) {

      $info = $this->bc->rpc->validateaddress("$address");

      if ($info->isvalid) {

        if ($info->ismine) {
          $account = $this->bc->rpc->getaccount($address);
          $account_id = $this->bc->accounts->getId($account);
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
        //$this->trace("invalid address");
        echo "invalid address";
        die;
      }

    }

    return $address_id;
  }




  /**
  *
  * generic named alias to be overwritten in extending classes
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

  public function getId($address) {
    return $this->getAddressId($address);
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

  public function get($address, $refresh = false)
  {
    //$this->trace( __METHOD__." ".$address );
    if ($address) {
      $cached = $this->bc->cache->get((string)$address);
      if ($cached !== false && $refresh === false) {
        $address = new Address($this->bc, $cached);
      } else {
        $address = new Address($this->bc, (string)$address);
        $this->updateCached((string)$address, $address->toArray());
      }

      return $address;
    }
  }


  public function updateCached($what, $data)
  {
    $this->currency->cache->set( $this->_cachePrefix.$what, $data, false, 60 );
  }

  public function wipeCached($what)
  {
    $this->currency->cache->delete( $this->_cachePrefix.$what );
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
    //$this->trace(__METHOD__);

    $sentSQL = "select ".
        "distinct receivingVouts.vout_id as vout_id, ".
        "'sent' as type, ".
        "receivingAddresses.address as toAddress, ".
        "sendingAddresses.address as fromAddress, ".
        "receivingVouts.value as value,".
        //"(select time from transactions where transaction_id = receivingVouts.transaction_id) as txtime, ".
        //"(select txid from transactions where transaction_id = receivingVouts.transaction_id) as txid, ".
        //"(select confirmations from transactions where transaction_id = receivingVouts.transaction_id) as confirmations ".
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
    //$this->trace(__METHOD__);
    $sendingAddress = $this->bc->db->esc($sendingAddress);
    $filterSQL = $this->getFilterSQL($filters);
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
  * create function sendingAddresses (transactionId int)
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
  *       where transactions_vins.transaction_id = transactionId;
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
    //$this->trace(__METHOD__);
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

    ////$this->trace($receivedSQL);
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
    //$this->trace(__METHOD__);

    $receivingAddress = $this->bc->db->esc($receivingAddress);
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
    if (is_string((string)$address)) {

      $address = $this->bc->db->esc($address);
      $filterSQL = $this->bc->addresses->getFilterSQL($filters);
      $receivedSQL = $this->bc->addresses->getReceivedSQL("receivingAddresses.address = '$address'", $filterSQL);
      $sentSQL = $this->bc->addresses->getSentSQL("sendingAddresses.address = '$address'", $filterSQL);
      $ledgerSQL = "$receivedSQL union $sentSQL";
      return $this->bc->db->assocs($ledgerSQL);
    } else {
      $this->error('invalid address '.$address);
      return false;
    }
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
    //$this->trace(__METHOD__);

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
      "left outer join transactions on receivingVouts.transaction_id = transactions.transaction_id ".

    "where ($addressSQL) ".
      "and sendingVoutsAddresses.address_id != receivingVoutsAddresses.address_id ".
      "$filterSQL ".
      "group by receivingVoutsAddresses.address_id";
    ////$this->trace($sql);

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

  public function getReceivedTotal($receivingAddress, $filters = false)
  {
    //$this->trace(__METHOD__);
    $receivingAddress = $this->bc->db->esc($receivingAddress);
    $filterSQL = $this->getFilterSQL($filters);
    $sql = $this->getReceivedTotalSQL("receivingAddresses.address = '$receivingAddress' ", $filterSQL);
    $receivedTotal = $this->bc->db->value($sql);

    return $this->round($receivedTotal);
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
    //$this->trace(__METHOD__);

      $sql = "select sum(total) from (".
          "select sum(receivingVouts.value) as total ".
           "from addresses as sendingAddresses ".
            "inner join transactions_vouts_addresses on transactions_vouts_addresses.address_id = sendingAddresses.address_id ".
            "inner join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id ".
            "inner join transactions_vins on transactions_vins.vout_id = transactions_vouts.vout_id ".
            "left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id ".
            "left outer join transactions_vouts_addresses as receivingVoutAddresses on receivingVouts.vout_id = receivingVoutAddresses.vout_id ".
            "left outer join addresses as receivingAddresses on receivingVoutAddresses.address_id = receivingAddresses.address_id ".
            "left outer join transactions on receivingVouts.transaction_id = transactions.transaction_id ".
              "where $addressSQL and receivingVouts.value is not null $filterSQL ".
            " group by transactions_vouts_addresses.vout_id ".
        ") as totals";

    //$this->trace($sql);
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
    //$this->trace(__METHOD__);

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
    //$this->trace(__METHOD__);
    //todo: benchmark id based '$address_id = $this->bc->addresses->getId($address);'

    $sql = "select  ".
      "sum(vouts.value) ".
    "from addresses ".
      "inner join transactions_vouts_addresses as voutsAddresses on voutsAddresses.address_id = addresses.address_id ".
      "inner join transactions_vouts as vouts on voutsAddresses.vout_id = vouts.vout_id ".
      "inner join transactions on vouts.transaction_id = transactions.transaction_id ".
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
    //$this->trace(__METHOD__);
    //todo: benchmark id based '$address_id = $this->bc->addresses->getId($address);'

    $filterSQL = $this->getFilterSQL( $filters );

    //$this->trace("getUnspentTotal sql:");
    $sql = $this->unspentTotalSQL("addresses.address = '$address'", $filterSQL);
    $receivedTotal = $this->bc->db->value($sql);

    return $receivedTotal;
  }

  /**
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function prepDate($dateStr, $setTime = false)
  {
    //$this->trace(__METHOD__);

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

  /**
  *
  * assembles any filters passed into sql
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function getFilterSQL($filters)
  {
    //$this->trace(__METHOD__);

    $filterSQL = "";
    if (count($filters) > 0) {

      if (array_key_exists('startDate', $filters) && ($startDate = $this->prepDate($filters['startDate'], 'start'))) {
        $filterSQL .= "and (transactions.blocktime >= '$startDate' or transactions.time >= '$startDate')";
      }

      if (array_key_exists('endDate', $filters) && ($endDate = $this->prepDate($filters['endDate'], 'end'))) {
        $filterSQL .= "and (transactions.blocktime <= '$endDate' or transactions.time <= '$endDate')";
      }

      if (array_key_exists('txid', $filters)) {
        $filterSQL .= "and transactions.txid = '".$this->db->esc($txid)."' ";
      }

      if (array_key_exists('transaction_id', $filters) && is_numeric($filters['transaction_id'])) { //within a particular transaction
        $filterSQL .= "and transactions.transaction_id = ".$filters['transaction_id']." ";
      }

      if (array_key_exists('receivingAddress', $filters)) { // sent from $sendingAddress to $receivingAddress
        $filterSQL .= "and receivingAddresses.address = ? ";
      }

      if (array_key_exists('sendingAddress', $filters)) { // received from $sendingAddress
        $filterSQL .= "and sendingAddresses.address = '".$this->db->esc($filters['sendingAddress'])."' ";
      }

    }

    return $filterSQL;

  }

}
