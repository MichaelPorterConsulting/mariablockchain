<?php
require_once "myblockchain.php";

class Account extends MyBlockChainRecord
{
  var $account;

  public function __construct($account)
  {
    parent::__construct();

    $this->account = $account;
  }

  function getID($account)
  {
    self::log("Looking up account $account");
    $account_id = MyBlockChain::$db->getval("select account_id from accounts where name = '$account'");
    if (!$account_id)
    {
        $account_id = MyBlockChain::$db->doinsert("insert into accounts (name) values ('$account')");
    }
    return $account_id;
  }

}