<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Smindel\GIS\Service\TileRenderer;
use Smindel\GIS\Service\GDTileRenderer;
use Smindel\GIS\Service\ImagickTileRenderer;

if (class_exists('Imagick')) {
    Config::modify()->set(Injector::class, 'TileRenderer', ['class' => ImagickTileRenderer::class]);
}
