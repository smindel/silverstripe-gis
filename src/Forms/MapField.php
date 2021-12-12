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
     * The initial layer to show when editing.  The only other permitted value is 'satellite', anything else will
     * default to 'streets' in order that a map appears in the editing widget
     *
     * @var string
     */
    private static $initial_layer = 'streets';

    /**
     * Zoom level of the map widget if the MapField is empty
     *
     * @var int
     */
    private static $default_zoom = 13;

    /**
     * Whether the user can create complex gemoetries like e.g. MultiPoints
     *
     * @var boolean
     */
    protected $multiEnabled = false;

    protected $hideFormField = false;

    protected $controls = [
        'polyline' => true,
        'polygon' => true,
        'marker' => true,
        'circle' => false,
        'rectangle' => false,
        'circlemarker' => false
    ];

    public function Field($properties = [])
    {
        $type = $this->hideFormField ? 'hidden' : 'readonly';
        $this->setAttribute($type, $type);
        $srid = GIS::config()->default_srid;
        $proj = GIS::config()->projections[$srid];
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
        return GIS::config()->default_srid;
    }

    public function getDefaultLocation()
    {
        return json_encode(Config::inst()->get(MapField::class, 'default_location'));
    }

    public static function getDefaultZoom()
    {
        return Config::inst()->get(MapField::class, 'default_zoom');
    }

    public function enableMulti($enable = true)
    {
        $this->multiEnabled = $enable;
        return $this;
    }

    public function hideFormField($hide = true)
    {
        $this->hideFormField = $hide;
        return $this;
    }

    public function getMultiEnabled()
    {
        return $this->multiEnabled;
    }

    public function getInitialLayer()
    {
        return Config::inst()->get(MapField::class, 'initial_layer');
    }
}
