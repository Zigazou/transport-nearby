<?php

namespace Zigazou\TransportNearby\Exception;

/**
 * The "bad database" exception.
 */
class BadDatabaseException extends \RuntimeException {
  public function __construct(string $filename) {
    parent::__construct("Bad database: $filename");
  }
}
