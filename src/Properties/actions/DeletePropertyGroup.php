<?php

namespace DevGroup\DataStructure\Properties\actions;

use DevGroup\AdminUtils\actions\BaseAdminAction;
use DevGroup\DataStructure\models\PropertyGroup;
use DevGroup\DataStructure\Properties\Module;
use Yii;
use yii\web\NotFoundHttpException;

class DeletePropertyGroup extends BaseAdminAction
{
    public $listPropertyGroupsActionId = 'list-property-groups';

    /**
     * Deletes PropertyGroup model and redirects back to property group list
     *
     * @param integer $id
     * @param integer $applicablePropertyModelId
     *
     * @return \yii\web\Response
     * @throws \Exception
     */
    public function run($id, $applicablePropertyModelId)
    {
        $model = PropertyGroup::loadModel(
            $id,
            false,
            true,
            86400,
            new NotFoundHttpException("PropertyGroup model with specified id not found")
        );
        if ($model->delete() !== false) {
            Yii::$app->session->setFlash('warning', Module::t('app', 'Property group deleted.'));
        }
        return $this->controller->redirect(
            [
                $this->listPropertyGroupsActionId,
                'applicablePropertyModelId' => $applicablePropertyModelId,
            ]
        );
    }
}