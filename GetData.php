<?php

namespace mobile\modules\v2\actions\users;

use common\component\mobile\DatabaseExt;
use mobile\modules\v2\actions\BaseAction;

/**
 * @OA\Get(
 *      path="/user/get-data",
 *      summary="Получить данные исполнителя",
 *      tags={"Исполнитель"},
 *      @OA\RequestBody(
 *          @OA\MediaType(
 *              mediaType="application/json",
 *          ),
 *          @OA\MediaType(
 *              mediaType="application/xml",
 *          ),
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Успешный ответ",
 *      ),
 *      @OA\Response(
 *          response="400",
 *          description="Запрещено получать данные. Пользователь не авторизован",
 *      ),
 *      security={
 *          {"Access-Token": {}}
 *      },
 *  )
 *
 */
class GetData extends BaseAction
{
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }

        $driver = DatabaseExt::init()->getDriver();
        $parameters['appInfo'] = (array)DatabaseExt::init()->getAppInfo($driver);
        $parameters['diverInfo'] = (array)DatabaseExt::init()->getDriverInfo($driver, true);
        $parameters['avatar'] = (array)DatabaseExt::init()->getAvatarPhotos($driver, true);

        return $this->answer($parameters);
    }
}