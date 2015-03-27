<?php
/**
 * Address
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
 * A bitcoin based address
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class Address extends Object {

  /**
  * storage for the dynamically loaded 'address_id' property
  * @var int $_address_id primary key in the database
  * @since 0.1.0
  */
  protected $_address_id;

  /**
  * prefix for ids in memcache
  * @var str $_cachePrefix
  * @since 0.1.0
  */
  protected $_cachePrefix;

  /**
  * address string
  * @var string $address string version of this address
  * @since 0.1.0
  */
  public $address;

  /**
  * has the address passed validation
  * @var boolean $isValid true for passed, false for fail, null for unchecked
  * @since 0.1.0
  */
  public $isvalid;

  /**
  * is the address in the working account / wallet
  * @var boolean $ismine true if so
  * @since 0.1.0
  */
  public $ismine;

  /**
  * is script
  * @var int $isscript typical
  * @since 0.1.0
  */
  public $isscript;

  /**
  * public key
  * @var int $pubkey typical
  * @since 0.1.0
  */
  public $pubkey;

  /**
  * is compressed
  * @var boolean $iscompressed typical
  * @since 0.1.0
  */
  public $iscompressed;

  /**
  *
  * @name __construct
  * @param MariaBlockChain\MariaBlockChain $blockchain the blockchain scope
  * @param array $args additional arguments and overrides
  * @since 0.1.0
  * @return object
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function __construct($blockchain, $args)
  {
    parent::__construct($blockchain);
    if (is_numeric($args)) {
      $this->_address_id = $args;
      $this->_fromId();
    } else if (is_string($args)) {
      $this->address = $args;
      $info = $this->bc->addresses->getInfo($this->address);
      $this->_fromArray( $info );
    } else if (is_array($args) || $args instanceof \stdClass) {
      $this->_fromArray($args);
    }
  }

  /**
  * deprecated and *should* be completely phased out, confirm and toast
  * @name __get
  * @param str $var variable to retrieve
  * @since 0.1.0
  * @return mixed
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function __get($var)
  {
    //$this->trace(__METHOD__);

    switch ($var)
    {
      case 'address_id':

        if (!is_numeric($this->_address_id)) {
          $this->_address_id = $this->bc->addresses->getId($this->address);
          //$this->bc->log("got address_id fine");
        }
        return $this->_address_id;

      break;

      default:
        $this->error("attempt to access illegal property of Address $var");
      break;

    }
  }

  /**
  * returns address as string if requested as such
  * @name __get
  * @param str $var variable to retrieve
  * @since 0.1.0
  * @return str $this->address
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function __toString()
  {
    //$this->trace(__METHOD__);
    return (string)$this->address;
  }


  /**
  * serialize
  * @name toArray
  * @since 0.1.0
  * @return array
  */
  public function toArray()
  {
    $arr = [
      "address" => $this->address,
      "isvalid" => $this->isvalid,
      "ismine" => $this->ismine,
      "isscript" => $this->isscript,
      "pubkey" => $this->pubkey,
      "iscompressed" => $this->iscompressed,
      "address_id" => $this->address_id
    ];

    return $arr;
  }



  /**
  * load from id
  * @name _fromId
  * @since 0.1.0
  * @return void
  */
  private function _fromId()
  {
    $dbinfo = $this->bc->db->object("select * from addresses where address_id = ?", ['i', $this->_address_id]);
    $this->address = $dbinfo->address;
    $this->pubkey = $dbinfo->pubkey;
  }


  /**
  * load from array
  * @name _fromArray
  * @param str $arr variable to retrieve
  * @since 0.1.0
  * @return void
  */
  private function _fromArray($arr)
  {
    //$this->trace(__METHOD__);

    if (false === $arr instanceof stdClass) {
      $arr = (object) $arr;
    }

    if ($arr->isvalid) {
      foreach ($arr as $fld => $val) {
        $this->{$fld} = $val;
      }

      if (isset($arr->address_id)) {
        $this->_address_id = $arr->address_id;
      }
    } else {
      throw new \InvalidArgumentException('attempt to load invalid address from array '.json_encode($arr));
    }
  }
}
