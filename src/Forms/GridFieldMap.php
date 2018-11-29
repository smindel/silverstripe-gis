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
use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;

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
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet.markercluster.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/leaflet-search.js');
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/proj4.js');
        Requirements::customScript(sprintf('proj4.defs("EPSG:%s", "%s");', $epsg, $proj), 'EPSG:' . $epsg);
        Requirements::javascript('smindel/silverstripe-gis: client/dist/js/GridFieldMap.js');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/MarkerCluster.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/MarkerCluster.Default.css');
        Requirements::css('smindel/silverstripe-gis: client/dist/css/leaflet-search.css');

        return array(
            'header' => sprintf(
                '<div class="grid-field-map" data-map-center="%s" data-list=\'%s\'></div>',
                DBGeography::from_array(Config::inst()->get(DBGeography::class, 'default_location')),
                self::get_geojson_from_list($gridField->getList())
            ),
        );
    }

    public static function get_geojson_from_list($list)
    {
        $modelClass = $list->dataClass();

        $geometryField = array_search('Geography', Config::inst()->get($modelClass, 'db'));

        if (($epsg = Config::inst()->get(DBGeography::class, 'default_projection')) != 4326) {
            $projDef = Config::inst()->get(DBGeography::class, 'projections')[$epsg];
            $proj4 = new Proj4php();
            $proj4->addDef('EPSG:' . $epsg, $projDef);
            $proj = new Proj('EPSG:' . $epsg, $proj4);
        }

        $collection = [];

        foreach ($list as $item) {

            if (!$item->canView()) {
                continue;
            }

            if ($item->hasMethod('getWebServiseGeometry')) {
                $geometry = $item->getWebServiseGeometry();
            } else {
                $geometry = $item->$geometryField;
            }

            $array = DBGeography::to_array($geometry);

            if ($epsg != 4326) {
                if (strtolower($array['type']) == 'point') {
                    $point = new Point($array['coordinates'][0], $array['coordinates'][1], $proj);
                    $array['coordinates'] = $proj4->transform(new Proj('EPSG:4326', $proj4), $point)->toArray();
                } else {
                    foreach ($array['coordinates'] as &$coords) {
                        $point = new Point($coords[0], $coords[1], $proj);
                        $coords = $proj4->transform(new Proj('EPSG:4326', $proj4), $point)->toArray();
                    }
                    $array['coordinates'] = $array['coordinates'];
                }
            }

            $collection[$item->ID] = [
                $item->Title,
                $array['type'],
                $array['coordinates'],
            ];
        }

        return json_encode($collection);
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
