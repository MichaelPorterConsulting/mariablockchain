<?php
namespace WillGriffin\MariaBlockChain;

require_once "BlockChain.php";

class Address extends Object {

  protected $_address_id;
  protected $_cachePrefix;

  public $address;
  public $isvalid;
  public $ismine;
  public $isscript;
  public $pubkey;
  public $iscompressed;

  /**
  *
  *
  *
  *
  * @param BlockChain $blockchain
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

    //$this->trace(__METHOD__);

    if (is_numeric($args)) {
      $this->_address_id = $args;
      $this->_fromId();
    } else if (is_string($args)) {
      ////$this->trace("loading from string $args");
      $this->address = $args;
      $info = $this->bc->addresses->getInfo($this->address);
      $this->_fromArray( $info );
    } else if (is_array($args) || $args instanceof \stdClass) {
      $this->_fromArray($args);
    }
  }

  /**
  *
  *
  *
  *
  * @param string
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
  *
  *
  *
  *
  * @param string
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
  *
  *
  *
  *
  * @param string
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
   */
  public function toArray()
  {
    //$this->trace(__METHOD__);

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
  *
  * populates objects properties with returned info and returns validity boolean
  *
  *
  * @param string address related address to fetch the info
  *
  * <code>
  * <?php
  *
  * $address_id = Address::getInfo('mq7se9wy2egettFxPbmn99cK8v5AFq55Lx',0);
  *
  * ?>
  * </code>
   */
  public function validate()
  {
    //$this->trace(__METHOD__." {$this->address}");
    //todo: move to controller / toast

    $addrInfo = $this->bc->rpc->validateaddress("{$this->address}");
    if ($addrInfo->isvalid)
    {
      $this->address = $addrInfo->address;
      $this->isvalid = $addrInfo->isvalid;
      $this->ismine = $addrInfo->ismine;
      $this->isscript = $addrInfo->isscript;
      $this->pubkey = $addrInfo->pubkey;
      $this->iscompressed = $addrInfo->iscompressed;
      return true;
    } else {
      return false;
    }
  }

  /**
  *
  *
  *
  *
  * @param string
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
   */
  public function getId()
  {
    //$this->trace(__METHOD__." {$this->address}");
    //todo: move to controller / toast

    if (!is_numeric($this->_address_id)) {
      $this->_address_id = $this->bc->addresses->getId($this->address);
    }
    return $this->_address_id;
  }

  /**
  *
  *
  *
  *
  * @param string
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
   */
  private function _fromId()
  {
    //$this->trace(__METHOD__." {$this->address}");

    $dbinfo = $this->bc->db->object("select * from addresses where address_id = ?", ['i', $this->_address_id]);
    $this->address = $dbinfo->address;
    $this->pubkey = $dbinfo->pubkey;
  }


  /**
  *
  *
  *
  *
  * @param string
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
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
      throw new \InvalidArgumentException('attempt to load invalid address from array');
    }
  }
}
