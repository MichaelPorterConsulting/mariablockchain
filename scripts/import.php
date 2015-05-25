#!/usr/bin/php
<?php

require_once __DIR__."/../vendor/autoload.php";

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Rpc\RpcFactory;
use BitWasp\Bitcoin\Chain\Difficulty;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Script\OutputScriptFactory;

use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Buffertools\Buffer;

use \willgriffin\MariaInterface\MariaInterface;

$configFile = (count($argv) > 1) ? $argv[1] : false;
$x = (count($argv) > 2) ? intval($argv[2]) : 1;
$math = new Math();
$difficulty = new Difficulty($math);

if (file_exists($configFile)) {

  $config = (object)parse_ini_file($configFile);

  //$currency = Main::getCurrency($currencyName);
  $db = new MariaInterface ([
    "host" => $config->dbhost,
    "user" => $config->dbuser,
    "pass" => $config->dbpass,
    "port" => $config->dbport,
    "name" => $config->dbname
  ]);

  $bitcoind = RpcFactory::bitcoind(
    $config->rpchost,
    $config->rpcport,
    $config->rpcuser,
    $config->rpcpass);

  $network = NetworkFactory::create($config->magic_byte, $config->magic_p2sh_byte, $config->private_key_byte)
    ->setHDPubByte($config->hd_pub_byte)
    ->setHDPrivByte($config->hd_priv_byte)
    ->setNetMagicBytes($config->net_magic_bytes);

  Bitcoin::setNetwork($network);
  $nextBlockHash = $bitcoind->getblockhash($x);
  do {

    echo "Block $x\n";

    $blockhash = $nextBlockHash;
    $block = $bitcoind->getblock($blockhash);
    $blockHeader = $block->getHeader();
    $blockBits = $blockHeader->getBits();
    $blockTime = $blockHeader->getTimestamp();
    $nextBlockHash = $blockHeader->getNextBlock();

    $bvals = ['isiidsisdss',
      $blockHeader->getTimestamp(),             //i
      $blockHeader->getBlockHash(),             //s
      $block->getBuffer()->getSize(),           //i
      $x,                                       //i
      $blockHeader->getVersion(),               //d
      $blockHeader->getMerkleRoot(),            //s
      $blockHeader->getNonce(),                 //i
      $math->getCompact($blockBits),            //s
      $difficulty->getDifficulty($blockBits),   //d
      $blockHeader->getPrevBlock(),             //s
      $nextBlockHash                            //s
    ];

    $block_id = $db->value('select block_id from blocks where hash = ?', ['s', $blockhash]);
    if (!$block_id) {

      $bsql = "insert into blocks ".
        "(time, ".
        "hash, ".
        "size, ".
        "height, ".
        "version, ".
        "merkleroot, ".
        "nonce, ".
        "bits, ".
        "difficulty, ".
        "previousblockhash, ".
        "nextblockhash, ".
        "last_updated ".
      ") values (from_unixtime(?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now())";
      $block_id = $db->insert($bsql, $bvals);

    } else {

      $bsql = "update blocks set ".
        "time = ?, hash = ?, size = ?, height = ?, version = ?, ".
        "merkleroot = ?, nonce = ?, bits = ?, difficulty = ?, ".
        "previousblockhash = ?, nextblockhash = ?, last_updated = now() ".
        "where block_id = ?";

      $bvals[0] = $bvals[0].'i';
      $bvals[] = $block_id;

      $db->update($bsql, $bvals);

    }

    $txs = $block->getTransactions();
    for ($t = 0; $t < $txs->count(); $t++) {

      $tx = $txs->getTransaction($t);
      $txid = $tx->getTransactionId();
      //echo "\n","TXID: ",$txid,"\n";

      $txFlds = ['sisiiii',
        $blockhash,
        $block_id,
        $txid,
        $tx->getVersion(),
        $blockTime,
        $blockTime,
        $tx->getLockTime()
      ];

      $transaction_id = $db->value("select transaction_id ".
        "from transactions where txid = ?", ['s', $txid]);

      if (!$transaction_id) {

        $txSql = 'insert into transactions '.
          '(blockhash, '.
            'block_id, '.
            'txid, '.
            'version, '.
            'time, '.
            'blocktime, '.
            'locktime '.
          ') values (?, ?, ?, ?, from_unixtime(?), from_unixtime(?), ?)';
        $transaction_id = $db->insert($txSql, $txFlds);

      } else {

        $txSql = 'update transactions set '.
            'blockhash = ?, '.
            'block_id = ?, '.
            'txid = ?, '.
            'version = ?, '.
            'time = ?, '.
            'blocktime = ?, '.
            'locktime = ? '.
          'where transaction_id = ?';

        $txFlds[0] = $txFlds[0].'i';
        $txFlds[] = $transaction_id;
        $db->update($txSql, $txFlds);

      }

      //echo json_encode($insertTransactionFlds), "\n";
      /*
         ____        _               _
        / __ \      | |             | |
       | |  | |_   _| |_ _ __  _   _| |_ ___
       | |  | | | | | __| '_ \| | | | __/ __|
       | |__| | |_| | |_| |_) | |_| | |_\__ \
        \____/ \__,_|\__| .__/ \__,_|\__|___/
                        | |
                        |_|
      */
      $outputs = $tx->getOutputs();
      for ($o = 0; $o < $outputs->count(); $o++) {

        $output = $outputs->getOutput($o);

        $rawData = $output->getScript()->getHex();
        $rawSize = strlen($rawData);
        $hexgz = gzcompress($rawData);

        if ($rawSize > 8192) { //todo: not sure about this as a threshold, bandaid anyways
          $outputType = 'sketchy'; //todo: will have to find all the 'sketchy' outputs and figure out how to deal
                                   // with them at a later date, some might be legit
        } else {
          $outputScript = $output->getScript();
          $outputType = OutputScriptFactory::classify($outputScript)->classify();
        }

        if ($outputType === 'multisig') {
          $reqSigs = -1; //todo: figure out and fix
        } else {
          $reqSigs = 1;
        }

        //outputs
        $voutIdSQL = "select vout_id from transactions_vouts where transaction_id = ? and n = ?";
        $voutIdFlds = ['ii', $transaction_id, $o];
        $vout_id = $db->value($voutIdSQL, $voutIdFlds);

        $voFlds = ['isiisis',
          $transaction_id,
          $txid,
          $output->getValue(),
          $o,
          $reqSigs,
          $outputType,
          $hexgz
        ];

        if (!$vout_id) {
          $voSql = "insert into transactions_vouts ".
            "(transaction_id, txid, value, n, reqSigs, type, hexgz)".
            " values (?, ?, ?, ?, ?, ?, ?)";

          if ($outputType == 'sketchy') {
            $voFlds[(count($voFlds) - 1)] = "";
            $vout_id = $db->insert($voSql, $voFlds);
          } else {
            $vout_id = $db->insert($voSql, $voFlds);
          }

        } else {
          $voSql = "update transactions_vouts set transaction_id = ?, txid = ?, ".
            "value = ?, n = ?, reqSigs = ?, type = ?, hexgz = ? where vout_id = ?";
          $voFlds[0] = $voFlds[0].'i';
          $voFlds[] = $vout_id;

          if ($outputType == 'sketchy') {
            $voFlds[(count($voFlds) - 2)] = "";
            $db->update($voSql, $voFlds);
          } else {
            $db->update($voSql, $voFlds);
          }
        }

        if ($outputType !== 'sketchy') {
            $address = AddressFactory::getAssociatedAddress($output->getScript(), $network);
            //echo $address,"\n";
            $address_id = getAddressId($address);
            $aisql = "insert into transactions_vouts_addresses (vout_id, address_id) values (?, ?)";
            $db->insert($aisql, ['ii', $vout_id, $address_id]);
        }
      }

      /*
      *
      * inputs
      *
      */
      $inputs = $tx->getInputs();
      for ($i = 0; $i < $inputs->count(); $i++) {
        $input = $inputs->getInput($i);
        $inputVout = $input->getVout();
        $inputTxid = $input->getTransactionId();
        $inputSequence = $input->getSequence();
        $isCoinbase = $input->isCoinbase();
        $inputScript = $input->getScript();

        if ($inputTxid !== "0000000000000000000000000000000000000000000000000000000000000000" && is_numeric($inputVout)) {

          $vin_id = $db->value("select vin_id ".
            "from transactions_vins ".
            "where transaction_id = ? and txid = ? and vout = ?",
            ['isi', $transaction_id, $inputTxid, $inputVout]);

          $vin_vout_id = $db->value("select vout_id ".
            "from transactions_vouts ".
            "where txid = ? and n = ?",
            ['si', $inputTxid, $inputVout]);

          $vivals = ['isii',
            $transaction_id,
            $inputTxid,
            $inputVout,
            $vin_vout_id];

          if (!$vin_id) {

            $visql = "insert into transactions_vins ".
              "(transaction_id, txid, vout, vout_id) ".
              "values (?, ?, ?, ?)";
            $vin_id = $db->insert($visql, $vivals);

          } else {

            $visql = "update transactions_vins set ".
              "transaction_id = ?, ".
              "txid = ?, ".
              "vout = ?, ".
              "vout_id = ? ".
              "where vin_id = ?";

            $vivals[0] = $vivals[0].'i';
            $vivals[] = $vin_id;
            $db->update($visql, $vivals);

          }

          if ($vin_vout_id > 0) {
            $db->update(
              "update transactions_vouts set spentat = ? where vout_id = ?",
              ['si', $txid, $vin_vout_id]);
          }

        } else if ($isCoinbase) { // Generation

          //seems that this changed at some point, block 21106 on testnet to be exact
          if ($inputSequence > 0) {
            $inputVout = $inputSequence;
          }

          //echo "Input $i - Generation ($coinbase)\n";

          $vin_id = $db->value("select vin_id ".
            "from transactions_vins ".
            "where txid = ? and vout = ?",
            ['si', $inputTxid, $inputVout]);

          $viflds = ['isi', $transaction_id, $inputTxid, $inputVout];

          if (!$vin_id) {
            $visql = "insert into transactions_vins ".
              "(transaction_id, txid, vout) ".
              "values (?, ?, ?)";
            $vin_id = $db->insert($visql, $viflds);
          } else {
            $visql = "update transactions_vins set ".
              "transaction_id = ?, txid = ?, coinbase = ?, vout_id = ?".
              "where vin_id = ?";
            $viflds[0] = $viflds[0].'i';
            $viflds[] = $vin_id;
          }

        } else {
          var_dump($input);
          echo "Didn't understand input";
          die;
        }
      } //end inputs

    } // end transaction
    $x += 1;
  } while (!empty($nextBlockHash));


} else {
  echo "Usage: import.php <config file> [starting height]\n";
  die;
}



/**
* Gets the primary key in the database for an address, inserts a new record if need be
* @name getAddressId
* @param str $address address to retrieve and id for
* @since 0.1.0
* @return int database primary key for address
*
* <code>
* <?php
* $address_id = $blockchain->addresses->getId('1124fWAtrp31Apd35zkoYqw2jRerE97HE4');
* ?>
* </code>
*/
function getAddressId($address, $db = null)
{

  if ($db === null) {
    $db = $GLOBALS['db'];
  }

  $address_id = $db->value("select address_id ".
    "from addresses ".
    "where address = ?",
    ['s', $address]);

  if (!$address_id) {
    $address_id = $db->insert(
      "insert into addresses (address) values (?)",
      ['s', $address]);
  }

  return $address_id;
}
