<?php

namespace WillGriffin\MariaBlockChain;

class Common {

  private $_hooks;

  public function emit( $event, $args = false ) {
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

  public function hasHook($event) {
    if (is_array($this->_hooks[$event]) && count($this->_hooks[$event] > 0)) {
      return true;
    } else {
      return false;
    }
  }

  public function hook($event, $method) {
    $this->_hooks[$event][] = $method;
  }
}