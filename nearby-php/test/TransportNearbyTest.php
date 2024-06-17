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

  public function testDistance(): void
  {
    $this->assertEquals(
      0.0,
      TransportNearby::distance(
        49.44211427302438,
        1.1128162664422916,
        49.44211427302438,
        1.1128162664422916
      )
    );

    $this->assertEquals(
      100.40946864775566,
      TransportNearby::distance(
        49.43981292469885,
        1.0980776668724,
        49.43980933615112,
        1.099466350800285
      )
    );
  }

  public function testComparePositions(): void
  {
    $this->assertEquals(
      TransportNearby::DIRECTION_NONE,
      TransportNearby::comparePositions(
        49.44211427302438,
        1.1128162664422916,
        49.44211427302438,
        1.1128162664422916
      )
    );

    $this->assertEquals(
      TransportNearby::DIRECTION_NORTH,
      TransportNearby::comparePositions(
        49.44211427302438,
        1.1128162664422916,
        49.44256492236086,
        1.112777514980297
      )
    );

    $this->assertEquals(
      TransportNearby::DIRECTION_EAST,
      TransportNearby::comparePositions(
        49.44211427302438,
        1.1128162664422916,
        49.442134092944336,
        1.11417724041354
      )
    );

    $this->assertEquals(
      TransportNearby::DIRECTION_SOUTH,
      TransportNearby::comparePositions(
        49.44211427302438,
        1.1128162664422916,
        49.441403478226576,
        1.1127692367941273
      )
    );

    $this->assertEquals(
      TransportNearby::DIRECTION_WEST,
      TransportNearby::comparePositions(
        49.44211427302438,
        1.1128162664422916,
        49.44210357968021,
        1.1115986497461678
      )
    );
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
            'free' => 0,
            'distance' => 25.0,
            'lat' => 49.44329903078631,
            'lon' => 1.0986720509862948,
            'direction' => TransportNearby::DIRECTION_SOUTHWEST,
          ],
        ],
        'distance_min' => 25.0,
        'distance_max' => 25.0,
      ],
      '#noname' => [
        'points' => [
          0 => [
            'type' => 1,
            'free' => 0,
            'distance' => 65.0,
            'lat' => 49.442978,
            'lon' => 1.098388,
            'direction' => TransportNearby::DIRECTION_SOUTHWEST,
          ],
          1 => [
            'type' => 0,
            'free' => 1,
            'distance' => 66.0,
            'lat' => 49.44400637,
            'lon' => 1.098717453,
            'direction' => TransportNearby::DIRECTION_NORTHWEST,
          ],
          2 => [
            'type' => 0,
            'free' => 1,
            'distance' => 70.0,
            'lat' => 49.44347937,
            'lon' => 1.09992111,
            'direction' => TransportNearby::DIRECTION_EAST,
          ],
          3 => [
            'type' => 0,
            'free' => 1,
            'distance' => 71.0,
            'lat' => 49.44336770000001,
            'lon' => 1.0999304,
            'direction' => TransportNearby::DIRECTION_EAST,
          ],
          4 => [
            'type' => 0,
            'free' => 1,
            'distance' => 74.0,
            'lat' => 49.443131400000006,
            'lon' => 1.0998674,
            'direction' => TransportNearby::DIRECTION_SOUTHEAST,
          ],
          5 => [
            'type' => 0,
            'free' => 1,
            'distance' => 79.0,
            'lat' => 49.44402335,
            'lon' => 1.099561066,
            'direction' => TransportNearby::DIRECTION_NORTHEAST,
          ],
        ],
        'distance_min' => 65.0,
        'distance_max' => 65.0,
      ],
    ];

    $this->assertEquals($expected, $actual);
  }
}