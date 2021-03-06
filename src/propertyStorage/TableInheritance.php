<?php

namespace DevGroup\DataStructure\propertyStorage;

use DevGroup\DataStructure\behaviors\HasProperties;
use DevGroup\DataStructure\helpers\PropertiesHelper;
use DevGroup\DataStructure\helpers\PropertyStorageHelper;
use DevGroup\DataStructure\models\ApplicablePropertyModels;
use DevGroup\DataStructure\models\Property;
use DevGroup\DataStructure\models\PropertyGroup;
use DevGroup\DataStructure\Properties\Module;
use DevGroup\DataStructure\traits\PropertiesTrait;
use DevGroup\TagDependencyHelper\NamingHelper;
use DevGroup\TagDependencyHelper\TagDependencyTrait;
use Yii;
use yii\caching\ChainedDependency;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;
use yii\db\Schema;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class TableInheritance extends AbstractPropertyStorage
{
    public static $multipleMode = Property::MODE_ALLOW_SINGLE;

    /**
     * @inheritdoc
     */
    public function fillProperties(&$models)
    {
        if (count($models) === 0) {
            return;
        }

        /** @var \yii\db\ActiveRecord|\DevGroup\DataStructure\traits\PropertiesTrait|\DevGroup\TagDependencyHelper\TagDependencyTrait $firstModel */
        $firstModel = reset($models);

        $tableInheritanceRows = Yii::$app->cache->lazy(function () use ($firstModel, $models) {
            $rows = (new Query())->select('*')
                ->from($firstModel->tableInheritanceTable())
                ->where(PropertiesHelper::getInCondition($models))
                ->all($firstModel->getDb());

            return ArrayHelper::map($rows, 'model_id', function ($item) {
                return $item;
            });
        }, PropertiesHelper::generateCacheKey($models, 'ti_rows'), 86400, $firstModel->commonTag());

        // fill models with properties
        foreach ($models as &$model) {
            /** @var \yii\db\ActiveRecord|\DevGroup\DataStructure\traits\PropertiesTrait|\DevGroup\TagDependencyHelper\TagDependencyTrait $firstModel */
            $modelId = $model->id;

            if (isset($tableInheritanceRows[$modelId])) {
                $properties = $tableInheritanceRows[$modelId];

                foreach ($properties as $key => $value) {
                    // skip model_id column
                    if ($key === 'model_id') {
                        continue;
                    }

                    /** @var Property $property */
                    $property = PropertiesHelper::getPropertyModel($model, $key);
                    if ($property === null) {
                        // skip unbinded property
                        continue;
                    }
                    $value = Property::castValueToDataType($value, $property->data_type);
                    $model->$key = $value;
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteAllProperties(&$models)
    {
        /** @var \yii\db\Command $command */
        if (count($models) === 0) {
            return;
        }

        /** @var \yii\db\ActiveRecord|\DevGroup\DataStructure\traits\PropertiesTrait $firstModel */
        $firstModel = reset($models);

        $command = $firstModel->getDb()->createCommand()
            ->delete($firstModel->tableInheritanceTable(), PropertiesHelper::getInCondition($models));

        $command->execute();
    }

    /**
     * @param ActiveRecord[]|\DevGroup\DataStructure\traits\PropertiesTrait[]|\DevGroup\DataStructure\behaviors\HasProperties[] $models
     *
     * @return boolean
     */
    public function storeValues(&$models)
    {
        if (count($models) === 0) {
            return true;
        }

        /** @var \yii\db\ActiveRecord|\DevGroup\DataStructure\traits\PropertiesTrait|\DevGroup\TagDependencyHelper\TagDependencyTrait $firstModel */
        $firstModel = reset($models);

        $db = $firstModel->getDb();

        /** @var \yii\db\Command[] $queries */
        $queries = [];

        $existRows = (new Query())->select('model_id')
            ->from($firstModel->tableInheritanceTable())
            ->where(PropertiesHelper::getInCondition($models))
            ->column($firstModel->getDb());

        foreach ($models as $model) {
            /** @var \yii\db\ActiveRecord|\DevGroup\DataStructure\traits\PropertiesTrait|\DevGroup\DataStructure\traits\PropertiesTrait $model */
            $model->ensurePropertiesAttributes();

            $modelTableInheritancePairs = [];

            foreach ($model->propertiesAttributes as $propertyId => $key) {
                $property = Property::findById($propertyId);
                if ($property->storage_id === $this->storageId) {
                    // check if this property changed
                    if (in_array($propertyId, $model->changedProperties)) {
                        $modelTableInheritancePairs[$key] = $model->$key;
                    }
                }
            }
            if (count($modelTableInheritancePairs) > 0) {
                if (in_array($model->id, $existRows) === true) {
                    $queries[] = $db->createCommand()->update(
                        $firstModel->tableInheritanceTable(),
                        $modelTableInheritancePairs,
                        [
                            'model_id' => $model->id
                        ]
                    );
                } else {
                    $modelTableInheritancePairs['model_id'] = $model->id;
                    $queries[] = $db->createCommand()
                        ->insert(
                            $firstModel->tableInheritanceTable(),
                            $modelTableInheritancePairs
                        );
                }
            }
        }
        if (count($queries) > 0) {
            $db->transaction(function ($db) use ($queries) {
                foreach ($queries as $query) {
                    $query->execute();
                }
            });
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function beforePropertyValidate(Property &$property)
    {
        if ($property->allow_multiple_values) {
            $property->addError(
                'allow_multiple_values',
                Module::t('app', 'Property can\'t has multiple values if storage type is Table Inherited Row.')
            );
            return false;
        }
        return true;
    }

    /**
     * @param \yii\db\Connection $db
     * @param integer $type
     * @return string
     */
    protected static function columnTypeForDataType($db, $type)
    {
        $schema = $db->getSchema();
        switch ($type) {
            case Property::DATA_TYPE_FLOAT:
                return $schema->createColumnSchemaBuilder(Schema::TYPE_FLOAT);
            case Property::DATA_TYPE_INTEGER:
                return $schema->createColumnSchemaBuilder(Schema::TYPE_INTEGER);
            case Property::DATA_TYPE_STRING:
                /** @var \yii\db\ColumnSchemaBuilder $builder */
                return $schema->createColumnSchemaBuilder(Schema::TYPE_STRING);
            case Property::DATA_TYPE_BOOLEAN:
                return $schema->createColumnSchemaBuilder(Schema::TYPE_BOOLEAN);
            case Property::DATA_TYPE_TEXT:
            case Property::DATA_TYPE_PACKED_JSON:
                return $schema->createColumnSchemaBuilder(Schema::TYPE_TEXT);
            default:
                return $schema->createColumnSchemaBuilder(Schema::TYPE_TEXT);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteProperties($models, $propertyIds)
    {
        $columns = Property::find()
            ->select(new Expression('""'))
            ->where(
                [
                    'id' => $propertyIds,
                    'storage_id' => $this->storageId,
                ]
            )
            ->indexBy('key')
            ->column();
        if (count($columns) > 0) {
            foreach ($models as $model) {
                $model->getDb()->createCommand()->update(
                    $model->tableInheritanceTable(),
                    $columns,
                    [
                        'model_id' => $model->id,
                    ]
                )->execute();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function afterPropertyDelete(Property &$property)
    {
        $propertyGroups = PropertyGroup::find()->all();
        foreach ($propertyGroups as $propertyGroup) {
            static::dropColumn($property, $propertyGroup);
        }
    }

    /**
     * @param Property $property
     * @param PropertyGroup $propertyGroup
     */
    public static function afterBind($property, $propertyGroup)
    {
        static::addColumn($property, $propertyGroup);
    }

    /**
     * @param Property $property
     * @param PropertyGroup $propertyGroup
     */
    public static function afterUnbind($property, $propertyGroup)
    {
        static::dropColumn($property, $propertyGroup);
    }

    /**
     * @param PropertyGroup $propertyGroup
     * @return ActiveRecord|HasProperties|PropertiesTrait
     */
    public static function getModelClassNameByGroup($propertyGroup)
    {
        $className = ApplicablePropertyModels::find()
            ->select('class_name')
            ->where(['id' => $propertyGroup->applicable_property_model_id])
            ->scalar();
        if ($className === null) {
            throw new \Exception('Model with id {id} not found');
        }
        return $className;
    }

    /**
     * @param ActiveRecord|HasProperties|PropertiesTrait $className
     * @return bool
     * @throws \yii\db\Exception
     */
    public static function getColumns($className)
    {
        $schema = $className::getDb()
            ->getSchema()
            ->getTableSchema($className::tableInheritanceTable());
        return $schema->columnNames;
    }

    /**
     * @param Property $property
     * @param PropertyGroup $propertyGroup
     * @throws \Exception
     */
    public static function addColumn($property, $propertyGroup)
    {
        if ($property !== null && $propertyGroup !== null) {
            $className = static::getModelClassNameByGroup($propertyGroup);
            if (in_array($property->key, static::getColumns($className)) === false) {
                $className::getDb()
                    ->createCommand()
                    ->addColumn(
                        $className::tableInheritanceTable(),
                        $property->key,
                        static::columnTypeForDataType($className::getDb(), $property->data_type)
                    )
                    ->execute();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public static function getPropertyValuesByParams(
        $propertyId,
        $params = '',
        $customDependency = null,
        $customKey = '',
        $cacheLifetime = 86400
    ) {
        $property = Property::findById($propertyId);
        $column = $property->key;

        $params = static::prepareParams($params, $column);

        $classNames = static::getApplicablePropertyModelClassNames($propertyId);
        $queries = [];
        $keys = [$customKey, 'PropertyValues', 'Property', $propertyId, Json::encode($params)];
        $tags = [$property->objectTag()];
        foreach ($classNames as $className) {
            $columns = self::getColumns($className);

            if (array_search($column, $columns) !== false) {
                $query = new Query();
                $query->select($column)->from($className::tableInheritanceTable())->where($params);
                $queries[] = $query;
                $keys[] = $className;
                $tags[] = $className::commonTag();
            }
        }
        $dependency = self::dependencyHelper($customDependency, $tags);
        $query = self::unionQueriesToOne($queries);
        sort($keys);
        return Yii::$app->cache->lazy(
            function () use ($query) {
                return $query->column();
            },
            'TIPV_' . md5(Json::encode($keys)),
            $cacheLifetime,
            $dependency
        );
    }

    /**
     * @inheritdoc
     */
    public static function getModelsByPropertyValues(
        $propertyId,
        $values = [],
        $returnType = self::RETURN_ALL,
        $customDependency = null,
        $cacheLifetime = 86400
    ) {
        $result = $returnType === self::RETURN_COUNT ? 0 : [];
        $property = Property::findById($propertyId);
        $tags = [$property->objectTag()];
        $column = $property->key;
        $classNames = static::getApplicablePropertyModelClassNames($propertyId);
        foreach ($classNames as $className) {
            $tmpQuery = $className::find()->innerJoin(
                $className::tableInheritanceTable() . ' MP',
                'MP.model_id=' . $className::tableName() . '.id'
            )->where(["MP.$column" => $values]);
            $dependency = static::dependencyHelper(
                $customDependency,
                ArrayHelper::merge($tags, (array)$className::commonTag())
            );
            $result = static::valueByReturnType(
                $returnType,
                $tmpQuery,
                $result,
                $className,
                $dependency,
                $cacheLifetime
            );
        }

        return $result;
    }

    public static function getModelIdsByValues(
        $modelClass,
        $selections,
        $customDependency = null,
        $cacheLifetime = 86400
    ) {
        if (empty($selections)) {
            return false;
        }
        $storageId = PropertyStorageHelper::storageIdByClass(static::class);
        // build a cache key and make a available properties array
        $availableProperties = [];
        $cacheKey = 'GetModelIdsByValues:TableInheritance';
        foreach ($selections as $propertyId => $values) {
            if (count($values) < 1 || ($property = Property::findById(
                    $propertyId
                )) === null || $property->storage_id !== $storageId || (bool) $property->in_search === false
            ) {
                continue;
            }
            sort($values);
            $availableProperties[$propertyId] = $values;
            $cacheKey .= ':' . $propertyId . ':' . implode('-', $values);
        }
        if (empty($availableProperties)) {
            return false;
        }
        $result = Yii::$app->cache->get($cacheKey);
        if ($result === false) {
            $tags = [NamingHelper::getCommonTag($modelClass)];
            // build a query
            $query = (new Query())->select('model_id')->from($modelClass::tableInheritanceTable());
            foreach ($availableProperties as $propertyId => $values) {
                $property = Property::findById($propertyId);
                $tags[] = $property->objectTag();
                foreach($values as $value) {
                    $query->andWhere([$property->key => $value]);
                }
            }
            $result = $query->column();
            if ($customDependency === null) {
                $dependency = new TagDependency(['tags' => $tags]);
            } else {
                $dependency = new ChainedDependency(
                    [
                        'dependencies' => [
                            $customDependency,
                            new TagDependency(['tags' => $tags]),
                        ],
                    ]
                );
            }
            Yii::$app->cache->set($cacheKey, $result, $cacheLifetime, $dependency);
        }
        return $result;
    }
    /**
     * @param PropertiesTrait|TagDependencyTrait $modelClass
     * @param array $values
     * @todo  Add SMART cache here(hash values somehow)
     *
     * @return Query
     */
    public static function modelIdsQueryAtOnce($modelClass, $values)
    {
        $q = (new Query())
            ->select('model_id')
            ->from($modelClass::tableInheritanceTable());

        foreach ($values as $key => $vals) {
            $q->andWhere(['or', $key, $vals]);
        };

            $q->groupBy('model_id');
        return $q;
    }

    /**
     * @inheritdoc
     */
    public static function getModelIdsByContent(
        $modelClass,
        $propertyIds,
        $content,
        $intersect = false,
        $customDependency = null,
        $cacheLifetime = 86400
    )
    {
        if (empty($propertyIds)) {
            return false;
        }
        $storageId = PropertyStorageHelper::storageIdByClass(static::class);
        // build a cache key and make a available properties array
        $availableProperties = [];
        foreach ($propertyIds as $propertyId) {
            if (
                ($property = Property::findById($propertyId)) === null
                || $property->storage_id !== $storageId
            ) {
                continue;
            }
            $availableProperties[] = $propertyId;
        }
        if (empty($availableProperties)) {
            return false;
        }
        $cacheKey = 'GetModelIdsByContent:TableInheritance'
            . ':' . (int) $intersect . ':' . implode(':', $availableProperties);
        $result = Yii::$app->cache->get($cacheKey);
        if ($result === false) {
            $tags = [NamingHelper::getCommonTag($modelClass)];
            // build a query
            $query = (new Query())
                ->select('model_id')
                ->from($modelClass::tableInheritanceTable());
            foreach ($availableProperties as $propertyId) {
                $property = Property::findById($propertyId);
                $tags[] = $property->objectTag();
                if($intersect === true) {
                    $query->orWhere(['like', $property->key, $content]);
                } else {
                    $query->andWhere(['like', $property->key, $content]);
                }


            }
            $result = $query->column();
            if ($customDependency === null) {
                $dependency = new TagDependency(['tags' => $tags]);
            } else {
                $dependency = new ChainedDependency(
                    [
                        'dependencies' => [
                            $customDependency,
                            new TagDependency(['tags' => $tags])
                        ]
                    ]
                );
            }
            Yii::$app->cache->set($cacheKey, $result, $cacheLifetime, $dependency);
        }
        return $result;
    }

    /**
     * @param HasProperties|PropertiesTrait|\DevGroup\TagDependencyHelper\TagDependencyTrait|string|ActiveRecord $modelClass
     * @param array $selections
     * @param null $customDependency
     * @param int $cacheLifetime
     *
     * @return array|bool|false
     */
    public static function getModelIdsByRange(
        $modelClass,
        $selections,
        $customDependency = null,
        $cacheLifetime = 86400
    ) {
        if (empty($selections)) {
            return false;
        }
        $storageId = PropertyStorageHelper::storageIdByClass(static::class);
        // build a cache key and make a available properties array
        $availableProperties = [];
        $cacheKey = 'GetModelIdsByRange:TableInheritance';
        foreach ($selections as $propertyId => $values) {
            if (count($values) < 1 ||
                ($property = Property::findById($propertyId)) === null ||
                $property->storage_id !== $storageId ||
                (bool) $property->in_search === false
            ) {
                continue;
            }
            ksort($values);
            $availableProperties[$propertyId] = $values;
            $cacheKey .= ':' . $propertyId . ':' . implode('-', $values);
        }
        if (empty($availableProperties) === true) {
            return false;
        }
        $result = Yii::$app->cache->get($cacheKey);
        if ($result === false) {
            $tags = [NamingHelper::getCommonTag($modelClass)];
            if ($customDependency === null) {
                $dependency = new TagDependency(['tags' => $tags]);
            } else {
                $dependency = new ChainedDependency(
                    [
                        'dependencies' => [
                            $customDependency,
                            new TagDependency(['tags' => $tags]),
                        ],
                    ]
                );
            }
            // build a query
            $query = (new Query())->select('model_id')->from($modelClass::tableInheritanceTable());
            foreach ($availableProperties as $propertyId => $values) {
                $default = ['min' => ~PHP_INT_MAX, 'max' => PHP_INT_MAX];
                $values = ArrayHelper::merge($default, $values);
                if ($values['min'] > $values['max']) {
                    return [];
                }
                $property = Property::findById($propertyId);
                $tags[] = $property->objectTag();
                $query->andWhere(
                    [
                       'and',
                        [
                            '>=',
                            $property->key,
                            $values['min']
                        ],
                        [
                            '<=',
                            $property->key,
                            $values['max']
                        ]
                    ]

                );
            }
            $result = $query->column();
            Yii::$app->cache->set($cacheKey, $result, $cacheLifetime, $dependency);
        }
        return $result;
    }

    /**
     * @param Property $property
     * @param PropertyGroup $propertyGroup
     * @throws \Exception
     */
    public static function dropColumn($property, $propertyGroup)
    {
        if ($property !== null && $propertyGroup !== null) {
            $className = static::getModelClassNameByGroup($propertyGroup);
            if (in_array($property->key, static::getColumns($className)) === true) {
                $className::getDb()
                    ->createCommand()
                    ->dropColumn($className::tableInheritanceTable(), $property->key)
                    ->execute();
            }
        }
    }
}
