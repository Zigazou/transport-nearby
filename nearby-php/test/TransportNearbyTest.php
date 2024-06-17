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
            'type' => 'AST',
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
            'type' => 'AST',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
          1 => [
            'name' => 'F7',
            'long_name' => 'La Pléiade <> Hôtel de Ville de Sotteville',
            'school' => false,
            'type' => 'AST',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
          2 => [
            'name' => '27',
            'long_name' => 'Théâtre des Arts <> Bel Air',
            'school' => false,
            'type' => 'AST',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
          3 => [
            'name' => 'Noctambus',
            'long_name' => 'La Pléiade <> Cateliers',
            'school' => false,
            'type' => 'AST',
            'lat' => 49.4339230868,
            'lon' => 1.0918892934,
          ],
        ],
        'distance_min' => 2,
        'distance_max' => 2,
      ],

      'Rouen - Rive Gauche' => [
        'points' => [
          0 => [
            'name' => 'BlaBlaCar Bus',
            'long_name' => 'Paris - Roissy Charles de Gaulle Airport > Paris - la Défense Bus Station > Rouen',
            'school' => false,
            'type' => 'BBC',
            'lat' => 49.434093,
            'lon' => 1.092471,
          ],
        ],
        'distance_min' => 44,
        'distance_max' => 44,
      ],

      'Rouen (Avenue Champlain)' => [
        'points' => [
          0 => [
            'name' => 'FlixBus 1642',
            'long_name' => 'Paris - Rouen/Dieppe',
            'school' => false,
            'type' => 'FLX',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          1 => [
            'name' => 'FlixBus 762',
            'long_name' => 'Paris - Rouen - Etretat',
            'school' => false,
            'type' => 'FLX',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          2 => [
            'name' => 'FlixBus 742',
            'long_name' => 'Dieppe/Rouen - Paris/Paris Cdg',
            'school' => false,
            'type' => 'FLX',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          3 => [
            'name' => 'FlixBus 1742',
            'long_name' => 'Paris - Rouen',
            'school' => false,
            'type' => 'FLX',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          4 => [
            'name' => 'FlixBus 712',
            'long_name' => 'Brussels - Caen - Nantes',
            'school' => false,
            'type' => 'FLX',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          5 => [
            'name' => 'FlixBus 1759',
            'long_name' => 'Paris - Strasbourg',
            'school' => false,
            'type' => 'FLX',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          6 => [
            'name' => 'FlixBus N1306',
            'long_name' => 'Lublin - Frankfurt - Paris',
            'school' => false,
            'type' => 'FLX',
            'lat' => 49.434194,
            'lon' => 1.092699,
          ],
          7 => [
            'name' => 'FlixBus N709',
            'long_name' => 'Grenoble - Paris - Rouen',
            'school' => false,
            'type' => 'FLX',
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

  public function testHotelDeVille(): void
  {
    $transport = new TransportNearby(dirname(__FILE__) . "/test-transport.db");

    $actual = $transport->prettyFindCycleStops(
      49.44343032024553,
      1.0989513522256456,
      80.0
    );

    $expected = [
      '1 - Général de Gaulle' => [
        'points' => [
          0 => [
            'type' => 4,
            'name' => '1 - Général de Gaulle',
            'free' => 0,
            'distance' => 25,
            'lat' => 49.44329903078631,
            'lon' => 1.0986720509862948,
          ],
        ],
        'distance_min' => 25,
        'distance_max' => 25,
      ],
      '' =>   [
        'points' => [
          0 => [
            'type' => 1,
            'name' => NULL,
            'free' => 0,
            'distance' => 65,
            'lat' => 49.442978,
            'lon' => 1.098388,
          ],
          1 => [
            'type' => 0,
            'name' => NULL,
            'free' => 1,
            'distance' => 66,
            'lat' => 49.44400637,
            'lon' => 1.098717453,
          ],
          2 => [
            'type' => 0,
            'name' => NULL,
            'free' => 1,
            'distance' => 70,
            'lat' => 49.44347937,
            'lon' => 1.09992111,
          ],
          3 => [
            'type' => 0,
            'name' => NULL,
            'free' => 1,
            'distance' => 71,
            'lat' => 49.44336770000001,
            'lon' => 1.0999304,
          ],
          4 => [
            'type' => 0,
            'name' => NULL,
            'free' => 1,
            'distance' => 74,
            'lat' => 49.443131400000006,
            'lon' => 1.0998674,
          ],
          5 => [
            'type' => 0,
            'name' => NULL,
            'free' => 1,
            'distance' => 79,
            'lat' => 49.44402335,
            'lon' => 1.099561066,
          ],
        ],
        'distance_min' => 65,
        'distance_max' => 65,
      ],
    ];

    $this->assertEquals($expected, $actual);
  }
}