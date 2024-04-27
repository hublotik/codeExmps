<?php

namespace common\component\mobile;

use common\models\DriverList;
class DatabaseExt
{
    
.....................................................

    private static function getRelatedExecutors($driver)
    {
        $executorEntities = ExecutorEntity::find()->where(['driver_id' => $driver->ID])->all();
        $mainExecutorEntityId = DriverList::find()->where(['id' => $driver->ID])->one()?->mainPaymentDetail?->executor_entity_id;
        $executorDataList = [];

        foreach ($executorEntities as $executorEntity) {
            $isMain = 0;
            $paymentEntityName = isset($executorEntity->paymentEntityType) 
                ? $executorEntity->paymentEntityType->name : '';
            if($mainExecutorEntityId == $executorEntity->id){
                $isMain = 1;
            }
            $executorDataList[] = [
                'isMain'             => $isMain,
                'executorEntityName' => $executorEntity->name,
                'executorEntityId'   => $executorEntity->id,
                'paymentEntityName'  => $paymentEntityName,
                'paymentEntityId'    => $executorEntity->payment_entity,
            ];
        }
        return $executorDataList;
    }
}
