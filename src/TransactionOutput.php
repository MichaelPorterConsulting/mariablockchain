<?php
/**
 * TransactionOutput
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Transaction.php";


/**
* TransactionOutput
* @author willgriffin <https://github.com/willgriffin>
* @since 0.1.0
*/
class TransactionOutput extends Object
{

  /**
  * database vout_id
  * @var int $vout_id primary key in the transactions_vouts table
  * @since 0.1.0
  */
  public $vout_id;

  /**
  * database transaction_id
  * @var int $transaction_id primary key in the 'transactions' table
  * @since 0.1.0
  */
  public $transaction_id;

  /**
  * addresses authorizing the output
  * @var array $addresses each will have had to sign
  * @since 0.1.0
  */
  public $addresses;

  /**
  * scriptPubKey
  * @var string $scriptPubKey public key
  * @since 0.1.0
  */
  public $scriptPubKey;

  /**
  * amount of output
  * @var int $value satoshis to send
  * @since 0.1.0
  */
  public $value;

  /**
  * aka 'vout' in certain contexts .. index in transaction tx array
  * @var int $myParam This is my parameter
  * @since 0.1.0
  */
  public $n;

  /**
  * assembly
  * @var string $asm scriptSig assembly
  * @since 0.1.0
  */
  public $asm;

  /**
  * hex
  * @var string $hex scriptSig assembly
  * @since 0.1.0
  */
  public $hex;


  /**
  * hex
  * @var string $hex gzcompressed
  * @since 0.1.0
  */
  public $hexgz;


  /**
  * required signatures
  * @var int $reqSigs number of required signatures
  * @since 0.1.0
  */
  public $reqSigs;

  /**
  * type
  * @var string $type pubkeyhash|multisig|??
  * @since 0.1.0
  */
  public $type;

  /**
  * txid
  * @var string $txid transaction id
  * @since 0.1.0
  */
  public $txid;

  /**
  * spentat
  * @var string $spentat txid where output is spent if so
  * @since 0.1.0
  */
  public $spentat;

  /**
  * constructor
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain the scope
  * @param array $args properties to mix in
  * @since 0.1.0
  * @return void
  */
  public function __construct($blockchain, $args, $n = false)
  {
    $this->bc = $blockchain;
    $this->trace(__METHOD__);

    if (is_array($args) || $args instanceof \stdClass ) {
      $this->_loadArray($args);
    } else {
      throw new InvalidArgumentException('attempt to load invalid address from array');
    }
  }


  /**
  * as a stdClass
  * @name stdClass
  * @since 0.1.0
  * @return stdClass
  */
  public function stdClass()
  {
    $this->trace(__METHOD__);
    $flds = [
      "txid" => $this->txid,
      "n" => $this->n,
      "value" => $this->value,
      "scriptPubKey" => $this->scriptPubKey,
      "spentat" => $this->spentat
    ];

    if (count($this->addresses)) {
      $flds['addresses'] = array();
      foreach ($this->addresses as $address) {
        $flds['addresses'][] = (string) $address;
      }
    }
    return (object) $flds;
  }

  /**
  * mix in an array
  * @name _loadArray
  * @param array $arr field to set
  * @since 0.1.0
  * @return void
  */
  private function _loadArray($arr)
  {
    $this->trace(__METHOD__);

    if (false === $arr instanceof \stdClass) {
      $arr = (object) $arr;
    }

    $flds = ["transaction_id", "vout_id", "txid", "value", "n", "scriptPubKey", "spentat"];
    foreach ($flds as $fld) {
      if (isset($arr->{$fld})) {
        $this->{$fld} = $arr->{$fld};
      }
    }

    if (count($arr->scriptPubKey->addresses)) {
      foreach ($arr->scriptPubKey->addresses as $address) {
        $this->addresses[] = $this->bc->addresses->get($address);
      }
    }
  }

}
