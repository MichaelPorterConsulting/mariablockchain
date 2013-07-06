<?php

namespace MariaBlockChain;

require_once "BlockChain.php";

class Address extends BlockChainRecord
{

  var $address_id;
  var $address;
  var $isvalid;
  var $ismine;
  var $isscript;
  var $pubkey;
  var $iscompressed;
  var $account;

  public function __construct($address = false)
  {
    parent::__construct();
    if ($address)
    {
      $this->address = $address;
      $this->validate();
    }
  }

  public function validate()
  {
    $addrInfo = BlockChain::$bitcoin->validateaddress($address);
    if ($addrInfo['isvalid'])
    {
      $this->address = $addrInfo['address'];
      $this->isvalid = $addrInfo['isvalid'];
      $this->ismine = $addrInfo['ismine'];
      $this->isscript = $addrInfo['isscript'];
      $this->pubkey = $addrInfo['pubkey'];
      $this->iscompressed = $addrInfo['iscompressed'];
      return true;
    } else {
      return false;
    }
  }

  public static  function label($address, $label, $secret_id = 0, $private = 0)
  {
    if (!empty($address) && !empty($label) && is_numeric($secret_id))
    {
      $label = BlockChain::$db->esc($label);
      $address_id = Address::getID($address);
      return BlockChain::$db->insert("insert into addresses_labels (address_id, label, secret_id, private) values ($address_id, '$label', $secret_id, $private)");
    }
  }

  public static  function alias($address, $link_address, $secret_id = 0)
  {
    $address_id = Address::getID($address);
    $link_address_id = Address::getID($link_address);
    if (is_numeric($address_id) && is_numeric($link_address_id) && is_numeric($secret_id))
    {
      return BlockChain::$db->insert("insert into addresses_aliases (address_id, alias_address_id, secret_id) values ($address_id, $link_address_id, $secret_id)");
    }
  }

  public static  function getInfo($address)
  {
    self::log("Address::getInfo $address");
    $addrInfo = BlockChain::$bitcoin->validateaddress($address);
    return $addrInfo;
  }

  public static function getID($address)
  {
    self::log("Address::getID $address");
    $address_id = BlockChain::$db->value("select address_id from addresses where address = '$address'");

    if (!$address_id)
    {
      $info = BlockChain::$bitcoin->validateaddress($address);
      if ($info['isvalid'])
      {
        if ($info['ismine'])
        {
          $account = BlockChain::$bitcoin->getaccount($address);
          $account_id = Account::getID($account);
        } else {
          $account_id = 0;
        }

        //echo "account_id: $account_id\n";
        $address_id = BlockChain::$db->insert("insert into addresses (account_id, address, pubkey, ismine, isscript, iscompressed) values ($account_id, '$address', '".$info['pubkey']."',".intval($info['ismine']).",".intval($info['isscript']).",".intval($info['iscompressed']).")");
      } else {
        echo "invalid address";
        die;
      }

    }

    return $address_id;
  }


  /*
  public static function getLedger($address, $since = 0)
  {
    self::log("Address::getLedget $address $since");
    $ledgers = array();
    $address_id = self::getID($address);
    $asql = "select transactions_details.transaction_id as transaction_id, transactions.txid as txid, transactions.time as txtime, transactions_details.amount as amount, transactions_details.fee as fee from transactions_details inner join transactions on transactions_details.transaction_id = transactions.transaction_id where transactions_details.address_id = $address_id order by transactions.time desc";

    $entries = BlockChain::$db->assocs($asql);
    foreach ($entries as $entry)
    {
      if ($entries['amount'] > 0) // find source
      {
        $newLedger = $entry;

        $visql = "select transactions_vins.vin as vin, transactions_vins.vout as vout, transactions_vins.txid as txid from transactions_vins where transaction_id = ".$entry['transaction_id'];
        $virow = BlockChain::$db->gethash($visql);

        $vin_transaction_id = Transaction::getID($virow['txid']);
        $vinvout = BlockChain::$db->gethash("select * from transactions_vouts where transaction_id = $vin_transaction_id and n = ".$virow['vout']);

        $vinAddressSQL = "select addresses.address address from addresses inner join transactions_vouts_addresses on addresses.address_id = transactions_vouts_addresses.address_id where transactions_vouts_addresses.vout_id = ".$vinvout['vout_id'];
        $vinaddress = BlockChain::$db->getlist($vinAddressSQL);

        if (count($vinaddress) == 1){
          $vinaddress = $vinaddress[0];
          $newLedger['description'] = $vinaddress;
        } else {
          foreach ($vinaddress as $addr)
            $newLedger['description'] .= $addr." ";
          rtrim($newLedger['description']);
        }

        $newLedger['rid'] = uniqid();
        $newLedger['vout_id'] = $vinvout['vout_id'];
        #$newLedger['details'] = array(
        #  'vintxid' => $virow['txid'],
        #  'vinvout' => $virow['vout'],
        #  'vin' => $virow['vin'],
        #  'address' => $vinaddress
        #  );
        $ledgers[] = $newLedger;
      } else { // find destination
        $newLedger = $entry;

        $voutsql = "select transactions_vouts.vout_id as vout_id, transactions_vouts.n as n, transactions_vouts.txid as txid from transactions_vouts inner join transactions_vouts_addresses on transactions_vouts.vout_id = transactions_vouts_addresses.vout_id where transactions_vouts_addresses.address_id = $address_id and transactions_vouts.transaction_id = ".$entry['transaction_id'];

        $voutrow = BlockChain::$db->gethash($voutsql);

        $voutAddressSQL = "select addresses.address as address from addresses inner join transactions_vouts_addresses on addresses.address_id = transactions_vouts_addresses.address_id where transactions_vouts_addresses.vout_id = ".$voutrow['vout_id'];
        $voutaddress = BlockChain::$db->getlist($voutAddressSQL);

        if (count($voutaddress) == 1){
          $voutaddress = $voutaddress[0];
          $newLedger['description'] = $voutaddress;
        } else {
          foreach ($voutaddress as $addr)
            $newLedger['description'] .= $addr." ";
          rtrim($newLedger['description']);
        }

        $newLedger['rid'] = uniqid();
        $newLedger['vout_id'] = $voutrow['vout_id'];
        #$newLedger['details'] = array(
        #  'txid' => $voutrow['txid'],
        #  'vout' => $voutrow['vout'],
        #  'address' => $voutaddress
        #  );


        $ledgers[] = $newLedger;

      }
    }

    return $ledgers;
  }*/


  // way too slow and i'm not sure it works
  public static function slowGetLedger($address, $since = 0)
  {
    self::log("Address::slowGetLedger $address $since");
    $ledgerSQL = "
      (select concat(vins.vin_id,'-',vouts.vout_id,'-',voutAddresses.address_id) as rid, (vouts.value * -1) as amount, voutAddress.address as description, txs.time as txtime, txs.txid as txid, txs.confirmations as confirmations
      from addresses as voutAddress
        inner join transactions_vouts_addresses as voutAddresses on voutAddresses.address_id = voutAddress.address_id
        inner join transactions_vouts as vouts on vouts.vout_id = voutAddresses.vout_id
        inner join transactions as txs on txs.transaction_id = vouts.transaction_id
        inner join transactions_vins as vins on vins.transaction_id = txs.transaction_id
        inner join transactions_vouts as vinsVouts on vinsVouts.vout_id = vins.vout_id
        inner join transactions_vouts_addresses as vinsAddresses on vinsAddresses.vout_id = vinsVouts.vout_id
        inner join addresses as vinAddress on vinAddress.address_id = vinsAddresses.address_id
      where
        vinAddress.address = '$address')
      union
      (select concat(vins.vin_id,'-',vinsVouts.vout_id,'-',voutAddresses.address_id) as rid, vouts.value as amount, vinAddress.address as description,  txs.time as txtime, txs.txid as txid, txs.confirmations as confirmations
      from addresses as voutAddress
        inner join transactions_vouts_addresses as voutAddresses on voutAddresses.address_id = voutAddress.address_id
        inner join transactions_vouts as vouts on vouts.vout_id = voutAddresses.vout_id
        inner join transactions as txs on txs.transaction_id = vouts.transaction_id
        inner join transactions_vins as vins on vins.transaction_id = txs.transaction_id
        inner join transactions_vouts as vinsVouts on vinsVouts.vout_id = vins.vout_id
        inner join transactions_vouts_addresses as vinsAddresses on vinsAddresses.vout_id = vinsVouts.vout_id
        inner join addresses as vinAddress on vinAddress.address_id = vinsAddresses.address_id
      where
        voutAddress.address = '$address')
        order by txtime asc";

    return BlockChain::$db->assocs($ledgerSQL);

  }

  //todo: kill destroy die die
  //todo: make sure related transactions are up to date

  public function getLedger($address, $since = 0)
  {
    self::log("Address::getLedger $address $since");
    $ledgerSQL = "select addresses_ledger.ledger_id as ledger_id, addresses_ledger.amount as amount, transactions.txid as txid, transactions.time as txtime, transactions.confirmations as confirmations from addresses inner join addresses_ledger on addresses.address_id = addresses_ledger.address_id inner join transactions on addresses_ledger.transaction_id = transactions.transaction_id where addresses.address = '$address'";
    return BlockChain::$db->assocs($ledgerSQL);
  }


  public static function getSent($address)
  {

    //todo: optimize joins / inputs once testing is easier.. seems to work for now
    $sentSQL = "select
      receivingAddresses.address as address,
      receivingVouts.value as value,
      (select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime,
      (select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid,
      (select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations,
      receivingVouts.vout_id as vout_id
      from addresses as targetAddresses
      left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id
      left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id
      left join transactions_vins on transactions_vins.vout_id = transactions_vouts.vout_id
      left outer join transactions_vouts as receivingVouts on transactions_vins.transaction_id = receivingVouts.transaction_id
      left outer join transactions_vouts_addresses as receivingVoutAddresses on receivingVouts.vout_id = receivingVoutAddresses.vout_id
      left outer join addresses as receivingAddresses on receivingVoutAddresses.address_id = receivingAddresses.address_id
      where targetAddresses.address = \"".BlockChain::$db->esc($address)."\"";
      //echo json_encode(BlockChain::$db->assocs($sentSQL));
      return BlockChain::$db->assocs($sentSQL);
  }

  public static function getReceived($address)
  {
    //self::getInputs($address);
    //todo: optimize joins / inputs... experiment with 'change' more
    //todo: time in db relates to when the database record was added, fiddle with other options
    //todo: consider adding support for aliases
    $receivedSQL = "select
      sendingAddresses.address as address,
      transactions_vouts.value as value,
      (select time from transactions where transaction_id = transactions_vouts.transaction_id) as txtime,
      (select txid from transactions where transaction_id = transactions_vouts.transaction_id) as txid,
      (select confirmations from transactions where transaction_id = transactions_vouts.transaction_id) as confirmations,
      transactions_vouts.vout_id as vout_id
      from addresses as targetAddresses
      left join transactions_vouts_addresses on transactions_vouts_addresses.address_id = targetAddresses.address_id
      left join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id
      left outer join transactions_vins on transactions_vins.transaction_id = transactions_vouts.transaction_id
      left outer join transactions_vouts as sendingVouts on transactions_vins.transaction_id = sendingVouts.transaction_id
      left outer join transactions_vouts_addresses as sendingVoutAddresses on sendingVouts.vout_id = sendingVoutAddresses.vout_id
      left outer join addresses as sendingAddresses on sendingVoutAddresses.address_id = sendingAddresses.address_id
      where targetAddresses.address = \"".BlockChain::$db->esc($address)."\" and targetAddresses.address_id != sendingAddresses.address_id";
     // echo json_encode(BlockChain::$db->assocs($receivedSQL));

      //echo $receivedSQL;

      return BlockChain::$db->assocs($receivedSQL);
  }

}