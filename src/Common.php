<?php
/**
 * Common methods
 * @package MariaBlockChain
 * @version 0.1.0
 * @link https://github.com/willgriffin/mariablockchain
 * @author willgriffin <https://github.com/willgriffin>
 * @license https://github.com/willgriffin/mariablockchain/blob/master/LICENSE
 * @copyright Copyright (c) 2014, willgriffin
 */

namespace willgriffin\MariaBlockChain;

/**
 * Methods common to all classes
 * @author willgriffin <https://github.com/willgriffin>
 * @since 0.1.0
 */
class Common {

  /**
	 * _hooks
	 * @var array $_hooks associative array with event name as keys and array of hooks attach as value iirc
	 * @since 0.1.0
	 */
  private $_hooks;

  /**
  * convert from satoshi to btc
  * @name toSatoshi
  * @param float $satoshi amount of satoshi to convert
  * @since 0.1.0
  * @return float
  *
  * <code>
  * $block = self::toBtc(161803399);
  * </code>
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

    /**
    * convert from btc to satoshi
    * @name toSatoshi
    * @param float $btc amount of btc to convert
    * @since 0.1.0
    * @return int
    *
    * <code>
    * $block = self::toSatoshi(1.61803399);
    * </code>
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
  * emit an event
  * @name emit
  * @param string $event event to emit
  * @param mixed $args data relating to this instance of the event
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $block = $blockchain->block->get('00000000000000000400d9582bab30043c7f582892f234fedf7cc5cea88107af');
  * </code>
  */
  public function emit( $event, $args = false )
  {
    if ($this->hasHook($event)) {
      foreach ($this->_hooks[$event] as $hook) {
        if (is_callable($hook)) {
          call_user_func($hook, $args);
        } else {
          throw new \Exception("hook defined is not callable $event");
        }
      }
    }
  }

  /**
  * see if an event has any hooks attached
  * @name hasHook
  * @param string $event event to check
  * @since 0.1.0
  * @return boolean
  *
  * <code>
  * $block = $blockchain->block->get('00000000000000000400d9582bab30043c7f582892f234fedf7cc5cea88107af');
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
  * hook a function to an event
  * @name hook
  * @param string $event event name
  * @param function $method method to run on event
  * @since 0.1.0
  * @return void
  *
  * <code>
  * $block = $blockchain->block->get('00000000000000000400d9582bab30043c7f582892f234fedf7cc5cea88107af');
  * </code>
  */
  public function hook($event, $method)
  {
    $this->_hooks[$event][] = $method;
  }

  /**
  * Adopts the basic hooks (trace, error) of another object
  * @name prepDate
  * @since 0.1.0
  * @return void
  * @param object $what
  */
  public function adoptDefaultHooks($what)
  {

    $traceFunction = function($msg) use ($what) {
      $what->trace($msg);
    };

    $errorFunction = function($msg) use ($what) {
      $what->trace($msg);
    };

    $this->hook('trace', $traceFunction);
    $this->hook('error', $errorFunction);

  }


  /**
  * Takes in a date, represented however, gets it sql friendly. Optionally sets the time to beginning or end of the day.
  * only as smart as strtotime
  * @name prepDate
  * @param str $dateStr address in question
  * @param str $setTime additional filters
  * @since 0.1.0
  * @return string
  *
  * <code>
  * $sent = $blockchain->addresses->prepDate('April 20, 2005');
  * $sent = $blockchain->addresses->prepDate($timestamp);
  * </code>
  */
  public function prepDate($dateStr, $setTime = false)
  {

    if (!is_numeric($dateStr)) {
      $dateInt = strtotime($dateStr);
    } else {
      $dateInt = $dateStr;
    }

    if ($dateInt) {
      switch($setTime) {

        case 'end':
          $timeStr = '23:59:59';
        break;

        case 'start':
          $timeStr = '00:00:00';
        break;

        default:
          $timeStr = 'H:i:s';
        break;
      }

      return date("Y-m-d $timeStr", $dateInt);
    } else {
      $this->error("invalid date $dateStr"); //should never happen in production without someone fucking around
    }
  }



    /**
    * take note of something
    * @name msg
    * @param string $msg trace message
    * @since 0.1.0
    * @return void
    *
    * <code>
    * self::error('it happened');
    * </code>
    */
    public function trace( $msg )
    {
      if ($this->hasHook( 'trace' )) {
        $this->emit( 'trace', $msg );
      }
    }

    /**
    * handles a error
    * @name msg
    * @param string $msg error message
    * @since 0.1.0
    * @return void
    *
    * <code>
    * self::error('it hit the fan');
    * </code>
    */
    public function error( $msg )
    {
      if ($this->hasHook( 'error' )) {
        $this->emit('error', $msg);
      } else {
        throw new \Exception($msg);
      }
    }

}
