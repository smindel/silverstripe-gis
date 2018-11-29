<?php

namespace Smindel\GIS\Forms;

use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\ORM\FieldType\DBGeography;

class MapField extends FormField
{
    // protected $inputType = 'hidden';

    public function Field($properties = array())
    {
        $epsg = Config::inst()->get(DBGeography::class, 'default_projection');
        $proj = Config::inst()->get(DBGeography::class, 'projections')[$epsg];
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet-search.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.draw.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/proj4.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/wicket.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/MapField.js');
        Requirements::customScript(sprintf('proj4.defs("EPSG:%s", "%s");', $epsg, $proj), 'EPSG:' . $epsg);
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet-search.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.draw.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/MapField.css');
        return parent::Field($properties);
    }

    public function setValue($value, $data = null)
    {
        if ($value instanceof DBGeography) $value = $value->getValue();
        if (!$value) $value = DBGeography::from_array(Config::inst()->get(DBGeography::class, 'default_location'));

        $this->value = $value;
        return $this;
    }

    public static function getDefaultSRID()
    {
        return Config::inst()->get(DBGeography::class, 'default_projection');
    }
}
