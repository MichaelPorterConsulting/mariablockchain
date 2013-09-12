<?php

namespace WillGriffin\MariaBlockChain;

require_once "Transaction.php";

class TransactionInput extends BlockChainObject {

  public static $vins;

  /**
  *
  * get primary key id for transaction input. if not in the database inserts and grabs related tx
  *
  *
  * @param integer $transaction_id parent transaction primary key
  * @param array $vin associative array representing transaction input
  * @param integer distanceAway current recursion level
  * @param boolean $followtx whether to scan source transaction
  *
  * <code>
  *
  * $vout_id = TransactionOutput::getID(1, ...);
  *
  * </code>
   */
  public static function getID($tx, $vin, $distanceAway = 0, $followtx = true)
  {

    self::log("TransactionVin::getId $transaction_id".json_encode($vin));

    if ($vin->txid && isset($vin->vout))
    {
      $vinsID = $vin->txid."-".$vin->vout;
      if (isset(self::$vins[$vinsID]) && $vins[$vinsID]['vin_id'] > 0)
      {
        $vin_id = $vins[$vinsID]['vin_id'];
      } else {
        $vin_id = BlockChain::$db->value("select vin_id from transactions_vins where transaction_id = {$tx->transaction_id} and txid = '{$vin->txid}' and vout = {$vin->vout}");
        if (!$vin_id)
        {
          if ($followtx)
          {
            $vinvout_transaction_id = Transaction::getID($vin->txid, false, $distanceAway += 1);
            $vin_vout_id = BlockChain::$db->value("select vout_id from transactions_vouts where transaction_id = $vinvout_transaction_id and n = ".$vin->vout);
          } else {
            $vin_vout_id = null;
          }

          $visql = "insert into transactions_vins (transaction_id, txid, vout, asm, hex, sequence, coinbase, vout_id)  values ('".$tx->transaction_id."','".$vin->txid."','".$vin->vout."','".$vin->scriptSig->asm."','".$vin->scriptSig->hex."','".$vin->sequence."','".$vin->coinbase."', '$vin_vout_id')";
          $vin_id = BlockChain::$db->insert($visql);

          if ($vin_vout_id)
          {
            $vinvoutaddresses = BlockChain::$db->assocs("select transactions_vouts_addresses.address_id as address_id, transactions_vouts.value as amount from transactions_vouts_addresses inner join transactions_vouts on transactions_vouts_addresses.vout_id = transactions_vouts.vout_id where transactions_vouts_addresses.vout_id = $vin_vout_id");
            if (count($vinvoutaddresses) > 0)
            {
              foreach ($vinvoutaddresses as $vinaddress)
              {
                  $aisql = "insert into addresses_ledger (transaction_id, vout_id, vin_id, address_id, amount) values ({$tx->transaction_id}, $vin_vout_id, $vin_id, ".$vinaddress['address_id'].", ".$vinaddress['amount']." * -1)";
                  BlockChain::$db->insert($aisql);
              }
            }
          }
        }
      }
    } else if ($vin->sequence > 0 && !empty($vin->coinbase)) { // Generation
      $vinsID = $vin->txid."-".$vin->vout;
      if (isset(self::$vins[$vinsID]) && $vins[$vinsID]['vin_id'] > 0)
      {
        $vin_id = $vins[$vinsID]['vin_id'];
      } else {
        $vin_id = BlockChain::$db->value("select vin_id from transactions_vins where transaction_id = {$tx->transaction_id} and txid = '".$vin->txid."' and sequence = '".$vin->sequence."' and coinbase = '".$vin->coinbase."'");

        if (!$vin_id)
        {
          $visql = "insert into transactions_vins (transaction_id, sequence, coinbase, vout_id)  values ('".$tx->transaction_id."','".$vin->sequence."','".$vin->coinbase."', 0)";
          $vin_id = BlockChain::$db->insert($visql);

        }
      }
    } else {
      self::log("can not compute input ".json_encode($vin));
    }

    self::$vins["{$tx->transaction_id}-{$vin->vout}"]["vin_id"] = $vin_id;
    return $vin_id;
  }
}




//two of em