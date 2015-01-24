<?php

namespace WillGriffin\MariaBlockChain;

require_once "Account.php";

class AccountsController extends Object
{

  public function __construct($blockchain)
  {
    parent::__construct($blockchain);
  }

  /**
  *
  * get primary key id for account or creates if not yet in db
  *
  *
  * @param string account name of account to fetch id for
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
   */
  public function getId($account)
  {
    //$this->trace(__METHOD__);

    $account_id = $this->bc->db->value("select account_id from accounts where name = ?", ['s',$account]);
    if (!$account_id) {
      $account_id = $this->bc->db->insert("insert into accounts (name) values (?)", ['s', $account]);
    }
    return $account_id;
  }

  public function getLedger($account, $filters)
  {
    //$this->trace(__METHOD__." ".$account);

    if (empty($account)) {
      echo "invalid account";
      die;
    }

    $accountSubQuery = $this->addressesSQL($account);
    $sendingAddressesSQL = "sendingAddresses.address in ($accountSubQuery)";
    $receivingAddressesSQL = "receivingAddresses.address in ($accountSubQuery)";
    $filterSQL = $this->bc->addresses->getFilterSQL($filters);

    $sentSQL = $this->bc->addresses->getSentSQL($sendingAddressesSQL, $filterSQL);
    $receivedSQL = $this->bc->addresses->getReceivedSQL($receivingAddressesSQL, $filterSQL);
    $ledgerSQL = "$receivedSQL union $sentSQL";

    return $this->bc->db->assocs($ledgerSQL);
  }




  /**
  *
  * returns a subquery to include any addresses that are in account $account
  *
  *
  * @param string $account placed literally into the query to facilite naming a column in parent query.
  *                               could be a value but quotes will have to be included.
  *
  * <code>
  * <?php
  *
  *
  *
  * ?>
  * </code>
   */

  protected function addressesSQL($account)
  {
    //$this->trace(__METHOD__);

    $sql =  "select address ".
      "from accounts_addresses ".
      "where accounts_addresses.account = '".$this->bc->db->esc($account)."'";

    return $sql;
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

  public function getSent($account, $filters = false)
  {
    //$this->trace(__METHOD__);

    $accountSubQuery = $this->addressesSQL($account);
    $sql = $this->bc->addresses->getSentSQL("sendingAddresses.address in ($accountSubQuery)", $filterSQL);
    return $this->bc->db->assocs($sql);
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

  public function getReceived($account, $filters = false)
  {
    //$this->trace(__METHOD__);

    $accountSubQuery = $this->addressesSQL($account);
    $sql = $this->getReceivedSQL("receivingAddresses.address in ($accountSubQuery)", $filterSQL);
    return $this->bc->db->assocs($sql);
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

  public function getReceivedTotal($account, $fiters = false)
  {
    //$this->trace(__METHOD__);

    $accountSubQuery = $this->addressesSQL($account);
    $sql = $this->getReceivedTotalSQL(" receivingAddresses.address in ($accountSubQuery)", $filterSQL);
    $receivedTotal = $this->bc->db->value($sql);

    return $receivedTotal;

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

  public function getSentTotal($account, $filters = false)
  {
    //$this->trace(__METHOD__);

    $accountSubQuery = $this->addressesSQL($account);
    $sql = $this->getSentTotalSQL(" sendingAddresses.address in ($accountSubQuery)", $filterSQL);
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

  public function getUnspentTotal($account, $filters = false)
  {
    //$this->trace(__METHOD__);

    $accountSubQuery = $this->accountSubQuerySQL($account);
    $sql = $this->unspentTotalSQL(" addresses.address in ($accountSubQuery)", $filterSQL);
    $unspentTotal = $this->bc->db->value($sql);
    return $unspentTotal;
  }

}
