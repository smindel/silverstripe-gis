<?php

namespace Smindel\GIS\Forms;

use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\GIS;
use Smindel\GIS\ORM\FieldType\DBGeography;

class MapField extends FormField
{
    /**
     * Center for the map widget if the MapField is empty,
     * in Long, Lat (EPSG:4326)
     *
     * @var array
     */
    private static $default_location = [
        'lon' => 174.78,
        'lat' => -41.29
    ];

    /**
     * Whether the user can create complex gemoetries like e.g. MultiPoints
     *
     * @var boolean
     */
    protected $multiEnabled = false;

    protected $controls = [
        'polyline' => true,
        'polygon' => true,
        'marker' => true,
        'circle' => false,
        'rectangle' => false,
        'circlemarker' => false
    ];

    public function Field($properties = array())
    {
        $srid = Config::inst()->get(GIS::class, 'default_srid');
        $proj = Config::inst()->get(GIS::class, 'projections')[$srid];
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet-search.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.draw.1.0.4.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/proj4.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/wicket.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/MapField.js');
        Requirements::customScript(sprintf('proj4.defs("EPSG:%s", "%s");', $srid, $proj), 'EPSG:' . $srid);
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet-search.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.draw.1.0.4.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/MapField.css');
        return parent::Field($properties);
    }

    public function setValue($value, $data = null)
    {
        $this->value = $value instanceof DBGeography
            ? $value->getValue()
            : $value;

        return $this;
    }

    public function setControl($control, $enable = true)
    {
        if (array_key_exists($control, $this->controls)) {
            $this->controls[$control] = $enable;
        }
        return $this;
    }

    public function getControls()
    {
        return json_encode($this->controls);
    }

    public static function getDefaultSRID()
    {
        return Config::inst()->get(GIS::class, 'default_srid');
    }

    public function getDefaultLocation()
    {
        return json_encode(Config::inst()->get(MapField::class, 'default_location'));
    }

    public function enableMulti($enable = true)
    {
        $this->multiEnabled = $enable;
        return $this;
    }

    public function getMultiEnabled()
    {
        return $this->multiEnabled;
    }
}
