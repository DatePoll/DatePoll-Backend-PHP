<?php

namespace App\Validation;

use Illuminate\Validation\Validator as IlluminateValidator;

class Validator extends IlluminateValidator {
  /**
   * Backport the Laravel 12.60 email validation fix for CVE-2026-48019.
   */
  public function validateEmail($attribute, $value, $parameters) {
    if ((is_string($value) || $value instanceof \Stringable) && preg_match('/[\r\n]/', (string) $value)) {
      return false;
    }

    return parent::validateEmail($attribute, $value, $parameters);
  }
}
