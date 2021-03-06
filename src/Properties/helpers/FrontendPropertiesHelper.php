<?php

namespace DevGroup\DataStructure\Properties\helpers;

use DevGroup\DataStructure\models\PropertyHandlers;
use DevGroup\DataStructure\models\Property;
use DevGroup\DataStructure\models\PropertyStorage;
use DevGroup\DataStructure\Properties\Module;
use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/**
 * Class FrontendPropertiesHelper is a properties-related helper for frontend
 *
 * @package DevGroup\DataStructure\Properties\helpers
 */
class FrontendPropertiesHelper
{

    /**
     * Returns array of data types for select inputs
     * @return array
     */
    public static function dataTypeSelectOptions()
    {
        return [
            Property::DATA_TYPE_STRING => Module::t('app', 'String'),
            Property::DATA_TYPE_TEXT => Module::t('app', 'Text'),
            Property::DATA_TYPE_INTEGER => Module::t('app', 'Integer'),
            Property::DATA_TYPE_FLOAT => Module::t('app', 'Float'),
            Property::DATA_TYPE_BOOLEAN => Module::t('app', 'Boolean'),
            Property::DATA_TYPE_PACKED_JSON => Module::t('app', 'Packed JSON'),
            Property::DATA_TYPE_INVARIANT_STRING => Module::t('app', 'Untranslatable String'),
        ];
    }

    /**
     * Returns array of property handlers for select inputs(handler.id => handler.name)
     * @return array
     */
    public static function handlersSelectOptions()
    {
        return Yii::$app->cache->lazy(function () {
            $query = (new Query)
                ->select(['name', 'id'])
                ->from(PropertyHandlers::tableName())
                ->orderBy(['sort_order' => SORT_ASC])
                ->indexBy('id');
            $handlers = Module::getInstance()->handlersList;
            if (count($handlers) > 0) {
                $query->where(['class_name' => $handlers]);
            }
            return $query->column();
        }, 'PropertyHandlerId2Name', 86400, PropertyHandlers::commonTag());
    }

    public static function handlersSelectOptionOptions()
    {
        return Yii::$app->cache->lazy(function () {
            $rows = (new Query)
                ->select(['class_name', 'id'])
                ->from(PropertyHandlers::tableName())
                ->orderBy(['sort_order' => SORT_ASC])
                ->indexBy('id')
                ->column();
            foreach ($rows as $id => $className) {
                $rows[$id] = [
                    'data-type' => $className::$type,
                    'data-mode' => $className::$multipleMode,
                ];
            }
            return $rows;
        }, 'PropertyHandlerId2Option', 86400, PropertyHandlers::commonTag());
    }

    /**
     * Returns array of property storages for select inputs(storage.id => storage.name)
     * @return array
     */
    public static function storagesSelectOptions()
    {
        return Yii::$app->cache->lazy(function () {
            return (new Query)
                ->select(['name', 'id'])
                ->from(PropertyStorage::tableName())
                ->orderBy(['sort_order' => SORT_ASC])
                ->indexBy('id')
                ->column();
        }, 'PropertyStorageId2Name', 86400, PropertyStorage::commonTag());
    }

    public static function storagesSelectOptionOptions()
    {
        return Yii::$app->cache->lazy(function () {
            $rows = (new Query)
                ->select(['class_name', 'id'])
                ->from(PropertyStorage::tableName())
                ->orderBy(['sort_order' => SORT_ASC])
                ->indexBy('id')
                ->column();
            foreach ($rows as $id => $className) {
                $rows[$id] = [
                    'data-type' => $className::$type,
                    'data-mode' => $className::$multipleMode,
                ];
            }
            return $rows;
        }, 'PropertyHandlerId2Option', 86400, PropertyHandlers::commonTag());
    }
}
