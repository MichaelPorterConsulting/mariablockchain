<?php
/**
 * AccountsController
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Account.php";

/**
 * Utitlity methods relating to accounts
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class AccountsController extends Object
{

  /**
  *
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain the blockchain scope
  * @since 0.1.0
  * @return object
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function __construct($blockchain)
  {
    parent::__construct($blockchain);
  }

  /**
  * Gets the primary key in the database for an account, inserts a new entry if need be
  * @name getId
  * @param str $account account in question
  * @since 0.1.0
  * @return int database account_id for account
  * <code>
  * <?php
  * $account_id = $blockchain->accounts->getId('foo');
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

  /**
  * Get a list of ledger items for an account
  * @name getLedger
  * @param str $account account in question
  * @param array $filters query filters
  * @since 0.1.0
  * @return array associate array of ledger entries
  * <code>
  * <?php
  * $ledger = Account::getLedger('foo', [
  *             'startDate' => "2013-03-13",
  *             'endDate' => "2015-03-13" ]);
  * ?>
  * </code>
  */
  public function getLedger($account, $filters)
  {

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
  * Get sql query to return all addresses in an account
  * @name addressesSQL
  * @param str $account account in question
  * @since 0.1.0
  * @return str
  */
  protected function addressesSQL($account)
  {
    $sql =  "select address ".
      "from accounts_addresses ".
      "where accounts_addresses.account = '".$this->bc->db->esc($account)."'";

    return $sql;
  }

  /**
  * Get a list of 'sent' ledger entries for an account
  * @name getSent
  * @param str $account account in question
  * @param array $filters query filters
  * @since 0.1.0
  * @return array associate array of ledger entries
  * <code>
  * <?php
  * $sents = Account::getSent('foo', [
  *                    'startDate' => "2013-03-13",
  *                    'endDate' => "2015-03-13" ]);
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
  * Get a list of 'received' ledger entries for an account
  * @name getReceived
  * @param str $account account in question
  * @param array $filters query filters
  * @since 0.1.0
  * @return array associate array of ledger entries
  * <code>
  * <?php
  * $receiveds = Account::getReceived('foo', [
  *                    'startDate' => "2013-03-13",
  *                    'endDate' => "2015-03-13" ]);
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
  * Total received satoshi to an account
  * @name getReceivedTotal
  * @param str $account account in question
  * @param array $filters query filters
  * @since 0.1.0
  * @return object
  * <code>
  * <?php
  * $receivedTotal = Account::getReceivedTotal('foo', [
  *                    'startDate' => "2013-03-13",
  *                    'endDate' => "2015-03-13" ]);
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
  * Total satoshi sent from an account
  * @name getSentTotal
  * @param str $account account in question
  * @param array $filters query filters
  * @since 0.1.0
  * @return object
  * <code>
  * <?php
  * $sentTotal = Account::getSentTotal('foo', [
  *                'startDate' => "2013-03-13",
  *                'endDate' => "2015-03-13" ]);
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
  * Total satoshi of an accounts unspent outputs
  * @name getUnspentTotal
  * @param str $account account in question
  * @param array $filters query filters
  * @since 0.1.0
  * @return object
  * <code>
  * <?php
  * $receivedSQL = Address::getSentSQL('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx');
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
