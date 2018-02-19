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
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/wicket.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/MapField.js');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/MapField.css');
        return parent::Field($properties);
    }

    public function setValue($value, $data = null)
    {
        if ($value instanceof DBGeography) $value = $value->getValue();
        if (!$value) $value = DBGeography::fromArray(Config::inst()->get(DBGeography::class, 'default_location'));

        $this->value = $value;
        return $this;
    }

    public function getWidgetType()
    {
        return preg_match('/\bpoint\b/i', $this->value) ? 'point-picker' : 'polygon-editor';
    }
}
