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
