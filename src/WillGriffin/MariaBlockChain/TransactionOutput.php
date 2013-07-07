<?php

namespace WillGriffin\MariaBlockChain;

require_once "Transaction.php";

class TransactionOutput extends BlockChainObject {

  var $vout_id;
  var $transaction_id;
  var $value;
  var $n;
  var $asm;
  var $hex;
  var $reqSigs;
  var $type;
  var $txid;

  public static $vouts;

  public function __construct()
  {

  }

  /**
  *
  * get primary key id for transaction output, inserts if not represented yet
  *
  *
  * @param integer $transaction_id parent transaction primary key
  * @param array $vout associative array representing vout
  *
  * <code>
  *
  * $vout_id = TransactionOutput::getID(1, ...);
  *
  * </code>
   */
  public function getID($transaction_id, $vout)
  {
    self::log("TransactionVout::getId $transaction_id $vout");
    //echo "processing vout\n";
    $voutsID = $transaction_id."-".$vout;
    if (isset(self::$vouts["$voutsID"]) && self::$vouts["$voutsID"]['vout_id'] > 0)
    {
      $vout_id = self::$vouts["$voutsID"]['vout_id'];
    } else {
      $voutIDSQL = "select vout_id from transactions_vouts where transaction_id = $transaction_id and n = ".$vout['n'];
      $vout_id = BlockChain::$db->value($voutIDSQL);
      if (!$vout_id)
      {
        $vosql = "insert into transactions_vouts (transaction_id, txid, value, n, asm, hex, reqSigs, type) values (".$transaction_id.",'".$tx['txid']."','".$vout['value']."','".$vout['n']."','".$vout["scriptPubKey"]['asm']."','".$vout["scriptPubKey"]['hex']."','".$vout["scriptPubKey"]['reqSigs']."','".$vout["scriptPubKey"]['type']."')";
        //echo  "\n\n$vosql\n\n";
        $vout_id = BlockChain::$db->insert($vosql);

        foreach ($vout["scriptPubKey"]['addresses'] as $address)
        {
          $address_id = Address::getID($address);
          $aisql = "insert into transactions_vouts_addresses (vout_id, address_id) values ($vout_id, $address_id)";
          BlockChain::$db->insert($aisql);
          $aisql = "insert into addresses_ledger (transaction_id, vout_id, address_id, amount) values ($transaction_id, $vout_id, $address_id, (".$vout['value']."))";
          BlockChain::$db->insert($aisql);
          BlockChainRecord::$addressUpdates[] = $address;
        }
      }
    }

    return $vout_id;
  }

  public function getInfo()
  {

  }

}