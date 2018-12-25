<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Smindel\GIS\Service\TileRenderer;
use Smindel\GIS\Service\GDRenderer;
use Smindel\GIS\Service\ImagickRenderer;

if (class_exists('Imagick')) {
    Config::modify()->set(Injector::class, 'TileRenderer', ['class' => ImagickRenderer::class]);
}
