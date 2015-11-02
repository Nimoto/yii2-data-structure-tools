<?php

namespace DevGroup\DataStructure\propertyStorage;

use DevGroup\DataStructure\models\Property;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Class AbstractPropertyStorage
 *
 * @todo list:
 * - abstract function that for moving from one storage type to another
 *
 * @package DevGroup\DataStructure\propertyStorage
 */
abstract class AbstractPropertyStorage
{
    /**
     * @var int ID of storage in property_storage table
     */
    public $storageId = null;

    /**
     * @param int $storageId ID of storage in property_storage table
     */
    public function __construct($storageId)
    {
        $this->storageId = intval($storageId);
    }

    /**
     * Fills $models array models with corresponding binded properties.
     * Models in $models array should be the the same class name.
     *
     * @param ActiveRecord[]|\DevGroup\DataStructure\traits\PropertiesTrait[]|\DevGroup\DataStructure\behaviors\HasProperties[] $models
     *
     * @return ActiveRecord[]|\DevGroup\DataStructure\traits\PropertiesTrait[]|\DevGroup\DataStructure\behaviors\HasProperties[]
     */
    abstract public function fillProperties(&$models);

    /**
     * Removes all properties binded to models.
     * Models in $models array should be the the same class name.
     *
     * @param ActiveRecord[]|\DevGroup\DataStructure\traits\PropertiesTrait[]|\DevGroup\DataStructure\behaviors\HasProperties[] $models
     *
     * @return void
     */
    abstract public function deleteAllProperties(&$models);

    /**
     * @return string Returns class name
     */
    public static function className()
    {
        return get_called_class();
    }

    /**
     * @param ActiveRecord[]|\DevGroup\DataStructure\traits\PropertiesTrait[]|\DevGroup\DataStructure\behaviors\HasProperties[] $models
     *
     * @return boolean
     */
    abstract public function storeValues(&$models);

    /**
     * Action that should be done by property storage before property adding.
     * Property storage can override this function to add specific actions like schema manipulation.
     * @param Property $property Reference to property model
     * @return bool Success status, true if all's ok
     */
    public function beforePropertyAdd(Property &$property)
    {
        return true;
    }

    /**
     * Action that should be done by property storage after property adding.
     * Property storage can override this function to add specific actions like schema manipulation.
     * @param Property $property Reference to property model
     * @return void
     */
    public function afterPropertyAdd(Property &$property)
    {

    }

    /**
     * Action that should be done by property storage before property change.
     * Property storage can override this function to add specific actions like schema manipulation.
     * @param Property $property Reference to property model
     * @return bool Success status, true if all's ok
     */
    public function beforePropertyChange(Property &$property)
    {
        return true;
    }

    /**
     * Action that should be done by property storage after property change.
     * Property storage can override this function to add specific actions like schema manipulation.
     * @param Property $property Reference to property model
     * @return void
     */
    public function afterPropertyChange(Property &$property)
    {

    }

    /**
     * Action that should be done by property storage before property deletion.
     * Property storage can override this function to add specific actions like schema manipulation.
     * @param Property $property Reference to property model
     * @return bool Success status, true if all's ok
     */
    public function beforePropertyDelete(Property &$property)
    {
        return true;
    }

    /**
     * Action that should be done by property storage after property deletion.
     * Property storage can override this function to add specific actions like schema manipulation.
     * @param Property $property Reference to property model
     * @return void
     */
    public function afterPropertyDelete(Property &$property)
    {

    }

    /**
     * Action that should be done by property storage before property validation.
     * Property storage can override this function to add specific actions like redefining data type.
     * @param Property $property Reference to property model
     * @return bool Success status, true if all's ok
     */
    public function beforePropertyValidate(Property &$property)
    {
        return true;
    }

    /**
     * Action that should be done by property storage after property validation.
     * Property storage can override this function to add specific actions like redefining data type.
     * @param Property $property Reference to property model
     * @return void
     */
    public function afterPropertyValidate(Property &$property)
    {

    }
}
