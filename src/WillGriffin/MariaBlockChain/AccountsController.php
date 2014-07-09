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

}