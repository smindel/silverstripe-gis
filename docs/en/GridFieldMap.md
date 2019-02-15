# Smindel\GIS\Forms\GridFieldMap

![feature name](../images/GridFieldMap.png)

Map component for GridFields

## Methods

### public function GridFieldMap::\__construct($attribute = null)

Creates the component

- __$attribute__ (string) the name of geo field of the DataList's data class

## Example

This module doesn't come with an admin interface out of the box. But if you are using [silverstripe-admin](https://github.com/silverstripe/silverstripe-admin/), adding one is simple. Create a ModelAdmin and add the new GridFieldMap component to visualise a DataList on a map:

app/src/Admin/GISAdmin.php

```php
<?php

use SilverStripe\Admin\ModelAdmin;
use Smindel\GIS\Forms\GridFieldMap;
use Smindel\GIS\GIS;

class GISAdmin extends ModelAdmin
{
    private static $url_segment = 'gis';

    private static $menu_title = 'GIS';

    private static $managed_models = [
        City::class,
    ];

    public function getEditForm($id = NULL, $fields = NULL)
    {
        $form = parent::getEditForm($id, $fields);

        if (
            ($geometry = GIS::of($this->modelClass))
            && ($field = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass)))
        ) {
            $field->getConfig()->addComponent(new GridFieldMap());
        }

        return $form;
    }
}
```
