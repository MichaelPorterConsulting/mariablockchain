<?php
/**
 * Block
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

require_once "Object.php";

/**
 *
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class Block extends Object {

  /**
	 * __get && __set storage
	 * @var array $_vars
	 * @since 0.1.0
	 */
  private $_vars;

  /**
  * block hash
  * @var string $hash
  * @since 0.1.0
  */
  public $hash;

  /**
  * size
  * @var float $size
  * @since 0.1.0
  */
  public $size;

  /**
  * height
  * @var int $height
  * @since 0.1.0
  */
  public $height;

  /**
  * version
  * @var int $version
  * @since 0.1.0
  */
  public $version;

  /**
  * merkleroot
  * @var string $merkleroot
  * @since 0.1.0
  */
  public $merkleroot;

  /**
  * time
  * @var int $time
  * @since 0.1.0
  */
  public $time;

  /**
  * nonce
  * @var int $nonce
  * @since 0.1.0
  */
  public $nonce;

  /**
  * bits
  * @var int $bits
  * @since 0.1.0
  */
  public $bits;

  /**
  * difficulty
  * @var int $difficulty
  * @since 0.1.0
  */
  public $difficulty;

  /**
  * chainwork
  * @var string $chainwork
  * @since 0.1.0
  */
  public $chainwork;

  /**
  * previousblockhash
  * @var string $previousblockhash
  * @since 0.1.0
  */
  public $previousblockhash;

  /**
  * nextblockhash
  * @var string $nextblockhash
  * @since 0.1.0
  */
  public $nextblockhash;


  /**
  * tx
  * @var string $tx
  * @since 0.1.0
  */
  public $tx;


  /**
  * constructor
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain the blockchain scope
  * @param int|string|array $b a block_id|blockhash|array to load the block from
  * @since 0.1.0
  * @return void
  */
  public function __construct($blockchain, $b)
  {
    $this->bc = $blockchain;
    $this->_vars = [];

    if (is_numeric($b)) {
      $this->trace("loading block from id");
      $this->_vars['block_id'] = $b;
      //todo: $this->loadFromDatabase();
    } else if (is_string($b)) {
      $this->trace("loading block from string");
      $this->hash = $b;
      $this->_loadArray( $this->bc->blocks->getInfo($b) );
    } else if (is_array($b) || $b instanceof \stdClass) {
      $this->trace("loading block from array/object");
      $this->_loadArray($b);
    }
  }


  /**
  * lazy loaded variables, probably dumb and *should* by now be completely
  * phased out in favour of BlocksController methods
  * @name __get
  * @param string $var variable to retrieve
  * @since 0.1.0
  * @return mixed
  */
  public function __get($var)
  {
    switch ($var) {
      case 'block_id':
        if (!array_key_exists('block_id', $this->_vars)) {
          $this->_vars['block_id'] = $this->bc->blocks->getId($this->hash);
        }
        return $this->_vars['block_id'];
      break;

      default:
        $this->error("attempt to access illegal property of Block $var");
      break;
    }
  }

  /**
  * returns blockhash if request as string
  * @name __toString
  * @since 0.1.0
  * @return string
  */
  public function __toString()
  {
    return $this->hash;
  }

  /**
  * returns json representation of block
  * @name json
  * @since 0.1.0
  * @return string
  */
  public function json()
  {
    return json_encode( $this->stdClass() );
  }

  /**
  * as a stdClass
  * @name stdClass
  * @since 0.1.0
  * @return stdClass
  */
  public function stdClass()
  {
    return (object) $this->toArray();
  }

  /**
  * array for serializing
  * @name toArray
  * @since 0.1.0
  * @return stdClass
  */
  public function toArray()
  {
    $arr = [
      'hash' => $this->hash,
      'size' => $this->size,
      'height' => $this->height,
      'version' => $this->version,
      'merkleroot' => $this->merkleroot,
      'time' => $this->time,
      'nonce' => $this->nonce,
      'bits' => $this->bits,
      'difficulty' => $this->difficulty,
      'chainwork' => $this->chainwork,
      'previousblockhash' => $this->previousblockhash,
      'nextblockhash' => $this->nextblockhash,
      'tx' => $this->tx
    ];
    return $arr;
  }

  /**
  * load from array
  * @name _loadArray
  * @param $arr
  * @since 0.1.0
  * @return void
  */
  private function _loadArray($arr)
  {

    if (is_array($arr)) {
      $arr = (object) $arr;
    }

    $this->hash = $arr->hash;
    $this->size = $arr->size;
    $this->height = $arr->height;
    $this->version = $arr->version;
    $this->merkleroot = $arr->merkleroot;
    $this->time = $arr->time;
    $this->nonce = $arr->nonce;
    $this->bits = $arr->bits;
    $this->difficulty = $arr->difficulty;
    $this->chainwork = $arr->chainwork;
    $this->previousblockhash = $arr->previousblockhash;
    $this->nextblockhash = $arr->nextblockhash;
    foreach ($arr->tx as $tx) {
      $this->tx[] = $tx;
    }

  }

}
