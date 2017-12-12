<?php

namespace yiiunit\extension\swoole;

use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;

/**
 * This is the base class for all yii framework unit tests.
 */
class TestIdentity extends Component implements IdentityInterface
{
    public $id;
    public $name;

    public static function findIdentity($id)
    {
        if($id == 1){
            return self::testUser();
        }
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        if($token == 'test'){
            return self::testUser();
        }
        return null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        // TODO: Implement getAuthKey() method.
    }

    public function validateAuthKey($authKey)
    {
        // TODO: Implement validateAuthKey() method.
    }

    private static function testUser()
    {
        return new TestIdentity([
            'id'=> 1,
            'name'=>'test',
        ]);
    }
}
