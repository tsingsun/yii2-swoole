<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/21
 * Time: 下午2:39
 */

namespace yii\swoole\web;


/**
 * 由于实际测试中,并没有发现本处理方法有提高性能,不启用
 * @package yii\swoole\web
 */
class View extends \yii\web\View
{
    /**
     * @var array
     */
    public static $phpCodeCache = [];

    /**
     * 缓存文件+eval执行
     *
     * @inheritdoc
     */
    public function renderPhpFile($_file_, $_params_ = [])
    {
        ob_start();
        ob_implicit_flush(false);
        extract($_params_, EXTR_OVERWRITE);

        if (!YII_DEBUG && !isset(self::$phpCodeCache[$_file_])) {
            self::$phpCodeCache[$_file_] = '?>' . file_get_contents($_file_);
            eval(self::$phpCodeCache[$_file_]);
        }else{
            require($_file_);
        }
        return ob_get_clean();
    }
}