<?php

namespace WillGriffin\MariaBlockChain;

require_once "Account.php";

class AccountsController extends Object {

  public function __construct($blockchain) {

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
  public function getID($account)
  {
    $this->trace("Looking up account $account");
    $account_id = $this->bc->db->value("select account_id from wallet_accounts where name = ?", ['s',$account]);
    if (!$account_id) {
      $account_id = $this->bc->db->insert("insert into wallet_accounts (name) values (?)", ['s', $account]);
    }
    return $account_id;
  }

  public function getLedger($account)
  {
    $this->trace(__METHOD__);

    $sentSQL = $this->bc->addresses->getSentSQL("sendingAddresses.address in (select address from wallet_addresses where account = '$account')");
    $receivedSQL = $this->bc->addresses->getReceivedSQL("receivingAddresses.address in (select address from wallet_addresses where account = '$account')");
    $ledgerSQL = "$receivedSQL union $sentSQL";
    $this->trace($ledgerSQL);
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

    $sql =  "select address ".
      "from wallet_addresses ".
      "where wallet_addresses.account = '".$this->bc->db->esc($account)."')";

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
    $accountSubQuery = $this->accountSubQuerySQL($account);
    $sql = $this->unspentTotalSQL(" addresses.address in ($accountSubQuery)", $filterSQL);
    $unspentTotal = $this->bc->db->value($sql);
    return $unspentTotal;
  }

}