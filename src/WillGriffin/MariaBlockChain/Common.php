<?php

namespace WillGriffin\MariaBlockChain;

class Common {

  private $_hooks;

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
          $this->error("hook is not callable ($hook)");
          error_log($msg);
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
    if (is_array($this->_hooks[$event]) && count($this->_hooks[$event] > 0)) {
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