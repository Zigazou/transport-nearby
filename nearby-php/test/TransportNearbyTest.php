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

  public function testIleLacroix(): void
  {
    $transport = new TransportNearby(dirname(__FILE__) . "/test-transport.db");

    $actual = $transport->prettyFindStations(
      49.431359752332284,
      1.1042970880552083,
      10.0
    );

    $expected = [
      'Île Lacroix' => [
        'points' => [
          [
            'name' => '11',
            'long_name' => 'Collège Léonard de Vinci <> Île Lacroix',
            'school' => false,
            'type' => 'astuce',
            'lat' => 49.4313556209,
            'lon' => 1.1043073786,
          ]
        ],
        'distance_min' => 1,
        'distance_max' => 1,
      ],
    ];

    $this->assertEquals($expected, $actual);
  }

  public function testChamplain(): void
  {
    $transport = new TransportNearby(dirname(__FILE__) . "/test-transport.db");

    $actual = $transport->prettyFindStations(
      49.43393863741172,
      1.0919038158689358,
      70.0
    );

    $expected = [
      'Champlain' => [
        'points' => [
          0 => [
            'name' => 'F1',
            'long_name' => 'Plaine de la Ronce <> Stade Diochon',
            'school' => false,
            'type' => 'astuce',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
          1 => [
            'name' => 'F7',
            'long_name' => 'La Pléiade <> Hôtel de Ville de Sotteville',
            'school' => false,
            'type' => 'astuce',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
          2 => [
            'name' => '27',
            'long_name' => 'Théâtre des Arts <> Bel Air',
            'school' => false,
            'type' => 'astuce',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
          3 => [
            'name' => 'Noctambus',
            'long_name' => 'La Pléiade <> Cateliers',
            'school' => false,
            'type' => 'astuce',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
        ],
        'distance_min' => 2,
        'distance_max' => 2,
      ],

      'Rouen (Avenue Champlain)' => [
        'points' => [
          0 => [
            'name' => 'FlixBus 1642',
            'long_name' => 'Paris - Rouen/Dieppe',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          1 => [
            'name' => 'FlixBus 762',
            'long_name' => 'Paris - Rouen - Etretat',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          2 => [
            'name' => 'FlixBus 742',
            'long_name' => 'Dieppe/Rouen - Paris/Paris Cdg',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          3 => [
            'name' => 'FlixBus 1742',
            'long_name' => 'Paris - Rouen',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          4 => [
            'name' => 'FlixBus 712',
            'long_name' => 'Brussels - Caen - Nantes',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          5 => [
            'name' => 'FlixBus 1759',
            'long_name' => 'Paris - Strasbourg',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          6 => [
            'name' => 'FlixBus N1306',
            'long_name' => 'Lublin - Frankfurt - Paris',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          7 => [
            'name' => 'FlixBus N709',
            'long_name' => 'Grenoble - Paris - Rouen',
            'school' => false,
            'type' => 'flixbus',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
        ],
        'distance_min' => 64,
        'distance_max' => 64,
      ]
    ];

    $this->assertEquals($expected, $actual);
  }

}