<?php

namespace willgriffin\MariaBlockChain;

class Common {

  private $_hooks;





    /*
    * Convert from satoshi's to BTC
    *
    *
    */

    public function toBtc($satoshi)
    {

      $convert = function($val) {
        $converted = rtrim(bcdiv(intval($val), 100000000, 8),'0');
        return round(floatval($converted), 8);
      };

      if (is_numeric($satoshi)) {
          return $convert($satoshi);
      } else if (is_array($satoshi)) {
        foreach ($satoshi as $key => $val) {
          $btc[$key] = $convert($val);
        }
        return $btc;
      } else {
        throw new \Exception("attempt to convert an illegal value");
      }
    }

    /*
    * Convert from BTC to satoshi
    *
    *
    */

    public function toSatoshi($btc) {
      $convert = function($val) {
        return round($val * 1e8);
      };

      if (is_numeric($btc)) {
          return $convert($btc);
      } else if (is_array($btc)) {
        foreach ($btc as $key => $val) {
          $satoshi[$key] = $convert($val);
        }
        return $satoshi;
      }
    }


  /**
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function emit( $event, $args = false )
  {
    if ($this->hasHook($event)) {
      foreach ($this->_hooks[$event] as $hook) {
        if (is_callable($hook)) {
          call_user_func($hook, $args);
        } else {
          throw new \Execption("hook defined is not callable $event");
        }
      }
    }
  }

  /**
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function hasHook($event)
  {
    if (is_array($this->_hooks) && array_key_exists($event, $this->_hooks)) {
      return true;
    } else {
      return false;
    }
  }

  /**
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function hook($event, $method)
  {
    $this->_hooks[$event][] = $method;
  }

  /**
  *
  *
  *
  * @param
  *
  * <code>
  * <?php
  *
  *
  * ?>
  * </code>
  */
  public function round($value)
  {
    return round($value * 1e8) / 1e8;
  }

}
