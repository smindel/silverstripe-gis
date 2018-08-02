<?php

namespace Smindel\GIS\Forms;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\SS_List;
use Smindel\GIS\ORM\FieldType\DBGeography;

/**
 * GridFieldPaginator paginates the {@link GridField} list and adds controls
 * to the bottom of the {@link GridField}.
 */
class GridFieldMap implements GridField_HTMLProvider, GridField_DataManipulator
{
    use Configurable;

    protected $attribute;

    public function __construct($attribute)
    {
        $this->attribute = $attribute;
    }

    /**
     *
     * @param GridField $gridField
     * @return array
     */
    public function getHTMLFragments($gridField)
    {
        $epsg = Config::inst()->get(DBGeography::class, 'default_projection');
        $proj = Config::inst()->get(DBGeography::class, 'projections')[$epsg];

        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet-search.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.ajax.min.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/proj4.js');
        Requirements::customScript(sprintf('proj4.defs("EPSG:%s", "%s");', $epsg, $proj), 'EPSG:' . $epsg);
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/GridFieldMap.js');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet-search.css');

        return array(
            'header' => sprintf('<div class="grid-field-map" data-map-center="%s"></div>', DBGeography::fromArray(Config::inst()->get(DBGeography::class, 'default_location'))),
        );
    }

    /**
     * Manipulate the {@link DataList} as needed by this grid modifier.
     *
     * @param GridField $gridField
     * @param SS_List $dataList
     * @return DataList
     */
    public function getManipulatedData(GridField $gridField, SS_List $dataList)
    {
        return $dataList;
    }
}
