<?php

namespace backend\modules\dispatcher\controllers;

use common\models\AdditionalBankDetailsRF;
use common\models\AdditionalBankDetailsTR;
use common\models\AdditionalBankDetailsUZ;
use common\models\Bank;
use common\models\BankDetails;
use common\models\BankDetailsCountry;
use common\models\CardOwnerType;
use common\models\Country;
use Yii;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\widgets\ActiveForm;


class OwnerBankDetailsController extends BaseDispatherController
{

    public function beforeAction($action)
    {
        $this->layout = 'dispatcher';
        parent::beforeAction($action);
        $this->breadcrumbs[\Yii::t('dictionary','menu_main')] = Url::toRoute('/dispatcher/');
        $this->breadcrumbs[\Yii::t('dictionary', 'legal_entities')] = Url::toRoute('/dispatcher/card-owner-type');
        return $action;
    }

    /**
     * Таблица
     */
    public function actionIndex()
    {
        $this->currentTitle = \Yii::t('dictionary', 'entity_banking_details');
        $cardOwnerTypeId = \Yii::$app->request->get('id');
        return $this->render('list', ['cardOwnerTypeId' => $cardOwnerTypeId]);
    }

    /**
     * Выставление реквизита как основного (остальные автоматически isMain=0)
     */
    public function actionSetmain()
    {
        $id = \Yii::$app->request->get('id');
        $model = BankDetails::findOne(['id' => $id]);
        
        BankDetails::updateAll(['isMain' => 0], ['type_owner' => $model->type_owner]);

        $model->isMain = 1;
        $model->save();
        
        if (\Yii::$app->request->isAjax) {
            return self::jsonResponse(['status' => 200]);
        }
        
        $ownerEntityId = $model->type_owner;
        return $this->redirect([Url::toRoute("/dispatcher/owner-bank-details/?id={$ownerEntityId}")]);
    }

    /**
     * Создание
     */
    public function actionCreate()
    {
        $ownerEntityId = \Yii::$app->request->get('id');
        $ownerEntityId = explode('?', $ownerEntityId)[0];

        $this->breadcrumbs[\Yii::t('dictionary', 'entity_banking_details')] = Url::toRoute("/dispatcher/owner-bank-details/?id={$ownerEntityId}");
        $this->currentTitle = \Yii::t('dictionary', 'add');

        $model = new BankDetails();

        $lastId = BankDetails::find()->select('id')->orderBy(['id' => SORT_DESC])->one();
        $bankDetailId = $lastId->id + 1;

        $countries = BankDetailsCountry::find()->select(['id', 'country'])->asArray()->all();
        $countries = ArrayHelper::map($countries, 'id', 'country');
        foreach($countries as &$country) {
            $country = Yii::t('dictionary', strtolower($country));
        }
        return $this->render('edit', [
            'model'                => $model,
            'bankDetailId'         => $bankDetailId,
            'countryList'          => $countries,
            'ownerEntityId'        => $ownerEntityId
        ]);
    }

    /**
     * Проставляем первые реквизиты как основные при создании или удалении.
     */
    private function assignFirstAsMain(BankDetails $model, string $action = null) {
        $existedDetail = BankDetails::find()->where(['type_owner' => $model->type_owner, 
                                                     'is_deleted' => 0])
                                                     ->andWhere(['!=', 'id', $model->id])
                                                     ->one();
        if (!$existedDetail && in_array($action, ['create', 'restore'])) {
            $model->isMain = 1;
            if ($action == 'restore') $model->save();
        } elseif ($existedDetail && $action == 'delete') {
            $existedDetail->isMain = 1;
            $existedDetail->save();
        }   
    }

    /**
     * Валидация и сохранение моделей деталей банка.
     */
    public function actionAjaxmodelsdatasave()
    {
        if (\Yii::$app->request->isAjax) {
            $models = \Yii::$app->request->post('bankDetailsParams');
            $modelFields = $models['bankdetails'];
            $bankAdditionalFields = $models['additionalDetails'];

            $bankDetailsAtributes = [
                'type_owner' => $modelFields['typeOwnerId'],
                'name' => $modelFields['bankdetails-name'],
                'title' => $modelFields['bankdetails-title'],
                'country_id' => $modelFields['bankdetails-country_id'],
            ];
            
            $model = BankDetails::findOne($bankAdditionalFields['bankDetailId']);

            $model?->setAttributes($bankDetailsAtributes);
            $model ??= new BankDetails($bankDetailsAtributes);

            switch ($modelFields['bankdetails-country_id']) {
                case 3:
                    $bankAdditionalData = AdditionalBankDetailsUZ::find()
                        ->where(['banks_details_id' => $bankAdditionalFields['bankDetailId']])
                        ->one();
                    $additionalDataAtributes = $this->getAdditionalBankDetailsData('additionalbankdetailsuz', $bankAdditionalFields);
                    $bankAdditionalData?->setAttributes($additionalDataAtributes);
                    $bankAdditionalData ??= new AdditionalBankDetailsUZ($additionalDataAtributes);
                    break;
                case 4:
                    $bankAdditionalData = AdditionalBankDetailsTR::find()
                        ->where(['banks_details_id' => $bankAdditionalFields['bankDetailId']])
                        ->one();
                    $additionalDataAtributes = $this->getAdditionalBankDetailsData('additionalbankdetailstr', $bankAdditionalFields);
                    $bankAdditionalData?->setAttributes($additionalDataAtributes);
                    $bankAdditionalData ??= new AdditionalBankDetailsTR($additionalDataAtributes);
                    break;
                default:
                    $bankAdditionalData = AdditionalBankDetailsRF::find()
                        ->where(['banks_details_id' => $bankAdditionalFields['bankDetailId']])
                        ->one();
                    $additionalDataAtributes = $this->getAdditionalBankDetailsData('additionalbankdetailsrf', $bankAdditionalFields);
                    $bankAdditionalData?->setAttributes($additionalDataAtributes);
                    $bankAdditionalData ??= new AdditionalBankDetailsRF($additionalDataAtributes);
                    break;
            }

            if (!empty($bankAdditionalData) && !empty($model)) {
                ActiveForm::validate($model, $bankAdditionalData);
                $this->assignFirstAsMain($model, 'create');
                if (!$model->save() || !$bankAdditionalData->save()) {
                    return self::jsonResponse(['error' => \Yii::t('dictionary', 'model_not_saved')]);
                }
            }
            return self::jsonResponse(['status' => 200]);
        }
    }

    /**
     * Получение значений дополнительных полей additionalDetails из json.
     *
     * @param string $className jarviswidget-имя класса.
     * @param array $fields 
     * @return array
     */ 
    private function getAdditionalBankDetailsData($className, $fields) {
        $additionalDataArray = [
            'banks_details_id' => $fields['bankDetailId'],
            'bic' => $fields[$className . '-bic'] ?? null,
            'numberCor' => $fields[$className . '-numbercor'] ?? null,
            'numberRS' => $fields[$className . '-numberrs'] ?? null,
            'codeMFO' => $fields[$className . '-codemfo'] ?? null,
            'iban' => $fields[$className . '-iban'] ?? null,
            'account_number' => $fields[$className . '-account_number'] ?? null,
        ];
        $filteredAdditionalData = array_filter($additionalDataArray, function($value) {
            return $value !== null;
        });

        return $filteredAdditionalData;
    }

    /**
     * Редактирование
     */
    public function actionUpdate($id)
    {
        $this->currentTitle = \Yii::t('dictionary', 'edit_2');

        $model = BankDetails::findOne($id);
        $this->breadcrumbs[\Yii::t('dictionary', 'entity_banking_details')] = Url::toRoute("/dispatcher/owner-bank-details/?id={$model->type_owner}");
            
        $countries = BankDetailsCountry::find()->select(['id', 'country'])->asArray()->all();
        $countries = ArrayHelper::map($countries, 'id', 'country');
        foreach($countries as &$country) {
            $country = Yii::t('dictionary', strtolower($country));
        }
        $currentCountry = $model->country_id;
        return $this->render('edit', [
            'model'              => $model,
            'countryList'        => $countries,
            // 'bankAdditionalData' => $bankAdditionalData,
            'ownerEntityId'      => $model->type_owner,
            'bankDetailId'       => $model->id,
            'currentCountry'     => $currentCountry,
        ]);
    }

    public function actionDelete($id) {
        $model = BankDetails::findOne(['id' => $id]);
        $isDeleted = $model->is_deleted;
        $model->is_deleted = !$isDeleted ? 1 : 0;
        $model->isMain = 0;
        $model->save();
        if($model->is_deleted == 1) {
            $this->assignFirstAsMain($model, 'delete');
        } else {
            $this->assignFirstAsMain($model, 'restore');
        }
        if (\Yii::$app->request->isAjax ) return self::jsonResponse(['status' => 200]);
        $ownerEntityId = $model->type_owner;
        return $this->redirect([Url::toRoute("/dispatcher/owner-bank-details/?id={$ownerEntityId}")]);
    }

    public function actionAdditionaldetailsrf()
    {
        if (\Yii::$app->request->isAjax) {
            $bankDetailId = \Yii::$app->request->post('bankDetailId');
            $bankAdditionalData = AdditionalBankDetailsRF::find()->where(['banks_details_id' => $bankDetailId])->one()
                ?? new AdditionalBankDetailsRF();
            return $this->render('additional-details-rf', [
                'bankAdditionalData' => $bankAdditionalData,
                'bankDetailId'       => $bankDetailId,
            ]);
        }
    }

    public function actionAdditionaldetailsuz()
    {
        if (\Yii::$app->request->isAjax) {
            $bankDetailId = \Yii::$app->request->post('bankDetailId');
            $bankAdditionalData = AdditionalBankDetailsUZ::find()->where(['banks_details_id' => $bankDetailId])->one()
                ?? new AdditionalBankDetailsUZ();
            return $this->render('additional-details-uz', [
                'bankAdditionalData' => $bankAdditionalData,
                'bankDetailId'       => $bankDetailId,
            ]);
        }
    }

    public function actionAdditionaldetailstr()
    {
        if (\Yii::$app->request->isAjax) {
            $bankDetailId = \Yii::$app->request->post('bankDetailId');
            $bankAdditionalData = AdditionalBankDetailsTR::find()->where(['banks_details_id' => $bankDetailId])->one()
                ?? new AdditionalBankDetailsTR();
            return $this->render('additional-details-tr', [
                'bankAdditionalData' => $bankAdditionalData,
                'bankDetailId'       => $bankDetailId,
            ]);
        }
    }

    /**
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return BankDetails the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BankDetails::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(\Yii::t('dictionary', 'the_requested_page_does_not_exist'));
    }
}
