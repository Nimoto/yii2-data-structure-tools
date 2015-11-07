<?php

namespace DevGroup\DataStructure\Properties\controllers;

use DevGroup\DataStructure\Properties\actions\DeletePropertyGroup;
use DevGroup\DataStructure\Properties\actions\EditProperty;
use DevGroup\DataStructure\Properties\actions\EditPropertyGroup;
use DevGroup\DataStructure\Properties\actions\ListGroupProperties;
use DevGroup\DataStructure\Properties\actions\ListPropertyGroups;
use Yii;
use yii\web\Controller;

class ManageController extends Controller
{
    /**
     * This controller just uses actions in extension
     * @return array
     */
    public function actions()
    {
        return [
            'list-property-groups' => [
                'class' => ListPropertyGroups::className(),
            ],
            'edit-property-group' => [
                'class' => EditPropertyGroup::className(),
            ],
            'delete-property-group' => [
                'class' => DeletePropertyGroup::className(),
            ],
            'list-group-properties' => [
                'class' => ListGroupProperties::className(),
            ],
            'edit-property' => [
                'class' => EditProperty::className(),
            ]
        ];
    }
}
