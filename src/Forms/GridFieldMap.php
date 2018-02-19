<?php

namespace Smindel\GIS\Forms;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\SS_List;
use SilverStripe\Control\Controller;
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
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/GridFieldMap.js');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.css');
        return array(
            'header' => sprintf(
                '<div class="grid-field-map" data-map-center="%s" data-list-class="%s" data-edit-url="%s"></div>',
                DBGeography::fromArray(Config::inst()->get(DBGeography::class, 'default_location')),
                str_replace('\\', '-', $gridField->getList()->dataClass()),
                Controller::join_links($gridField->Link('item'), '$ID', 'edit')
            ),
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
