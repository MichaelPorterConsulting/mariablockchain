<?php

namespace WillGriffin\MariaBlockChain;

require_once "BlockChain.php";

class Account extends BlockChainObject
{
  var $account;

  public function __construct($account)
  {
    parent::__construct();

    $this->account = $account;
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
  * $account_id = Account::getID('foobar');
  *
  * ?>
  * </code>
   */
  public static function getID($account)
  {
    self::log("Looking up account $account");
    $account_id = BlockChain::$db->value("select account_id from wallet_accounts where name = '$account'");
    if (!$account_id)
    {
        $account_id = BlockChain::$db->insert("insert into wallet_accounts (name) values ('$account')");
    }
    return $account_id;
  }

}