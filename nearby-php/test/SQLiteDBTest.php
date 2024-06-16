<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Zigazou\TransportNearby\SQLiteDB;
use Zigazou\TransportNearby\Exception\FileNotFoundException;
use Zigazou\TransportNearby\Exception\BadDatabaseException;

final class SQLiteDBTest extends TestCase
{
  public function testFileNotFoundException(): void
  {
    $this->expectException(FileNotFoundException::class);
    new SQLiteDB(dirname(__FILE__) . "/xxxxxxxxxxxxxxxxxxxxxx");
  }

  public function testBadSQLiteDatabase(): void
  {
    $this->expectException(BadDatabaseException::class);
    new SQLiteDB(dirname(__FILE__) . "/bad-sqlite-database.db");
  }

  public function testQuery(): void
  {
    $db = new SQLiteDB(dirname(__FILE__) . "/valid-sqlite-database.db");
    $result = $db->query(
      "SELECT value FROM test WHERE id = :id",
      [":id" => 1]
    );

    $this->assertInstanceOf(\SQLite3Result::class, $result);
    $this->assertEquals("a", $result->fetchArray(SQLITE3_ASSOC)["value"]);
  }
}