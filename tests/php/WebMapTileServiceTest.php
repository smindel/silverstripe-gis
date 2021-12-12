<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\GIS;

// @phpcs:disable Generic.Files.LineLength.TooLong
class WebMapTileServiceTest extends FunctionalTest
{
    use RenderingAssertion;

    protected static $fixture_file = 'TestLocation.yml';

    public function setUp()
    {
        // reset GIS environment
        Config::modify()->set(GIS::class, 'default_srid', 4326);
        parent::setUp();
    }

    public static function getExtraDataObjects()
    {
        return [TestLocation::class];
    }

    public function testService()
    {
        $response = $this->get('webmaptileservice/Smindel-GIS-Tests-TestLocation/6/63/40.png');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/png', $response->getHeaders()['content-type']);

        $this->assertRenders($response->getBody(), 18, 18);
    }

    public function testCachedService()
    {
        $test_cache_path = 'test-tile-cache-' . uniqid();
        $full_path = TEMP_PATH . DIRECTORY_SEPARATOR . $test_cache_path;
        $full_name = $full_path . DIRECTORY_SEPARATOR . sha1(json_encode([]));
        Config::modify()->set(TestLocation::class, 'webmaptileservice', ['cache_ttl' => 100, 'cache_path' => $test_cache_path]);
        $response = $this->get('webmaptileservice/Smindel-GIS-Tests-TestLocation/6/63/40.png');
        Config::modify()->set(TestLocation::class, 'webmaptileservice', true);

        $this->assertTrue(is_dir($full_path), 'Cache path created');
        $this->assertTrue(is_readable($full_name), 'Cache file created');

        $this->assertRenders(file_get_contents($full_name), 18, 18);

        unlink($full_name);
        rmdir($full_path);
    }
}
