<?php

namespace Zigazou\TransportNearby\Exception;

/**
 * The "file not found" exception.
 */
class FileNotFoundException extends \RuntimeException {
  public function __construct(string $filename) {
    parent::__construct("File not found: $filename");
  }
}
