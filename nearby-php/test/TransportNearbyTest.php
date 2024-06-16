<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Zigazou\TransportNearby\TransportNearby;

final class TransportNearbyTest extends TestCase
{
  public function testTransportDatabaseOpen(): void
  {
    $transport = new TransportNearby(dirname(__FILE__) . "/test-transport.db");
    $this->assertInstanceOf(TransportNearby::class, $transport);
  }


}