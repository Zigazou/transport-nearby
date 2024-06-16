<?php

namespace Zigazou\TransportNearby\Exception;

/**
 * The "SQLiteDB" exception.
 */
class SQLiteDBException extends \RuntimeException {
  public function __construct(string $message) {
    parent::__construct("SQLiteDB error: $message");
  }
}