<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/22
 * Time: 上午11:13
 */

namespace yii\swoole\Controllers;

use Yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\helpers\Console;
use yii\swoole\server\Server;

/**
 * Usage: swoole/[actions] [options] [-c]=<file>
 *   swoole/start -c=@config/swoole.php project-name
 *   swoole/stop -c=@config/swoole.php project-name
 *
 *   -c <file>      look config file path,it can use alias path,if null,will get yii params config
 * @package yii\swoole\Controllers
 */
class SwooleController extends Controller
{
    /**
     * @var string swoole server config file path,can use yii alias Path
     */
    public $configPath;

    protected $config;

    public function beforeAction($action)
    {
        if(parent::beforeAction($action)){
            if($this->configPath){
                $path = Yii::getAlias($this->configPath);
                if(file_exists($path)){
                    $config = include $path;
                    if(!is_array($config)){
                        throw new InvalidConfigException('config file format not correct');
                    }
                    $this->config = $config;
                }else{
                    throw new InvalidConfigException('config file not exists');
                }
            }else{
                $this->config = Yii::$app->params['swoole'];
            }
            return true;
        }else{
            return false;
        }
    }


    public function options($actionID)
    {
        $options = ['configPath'];
        return array_merge($options,parent::options($actionID));
    }

    public function optionAliases()
    {
        $alias = [
            'c'=>'configPath',
        ];
        return array_merge($alias,parent::optionAliases());
    }

    /**
     * help
     */
    public function actionIndex()
    {
    }

    /**
     * start the server
     * @param string $siteName unique site name
     * @see https://wiki.swoole.com/wiki/page/19.html
     */
    public function actionStart($siteName)
    {
        return $this->runCommand($siteName,'start');
    }

    public function actionStop($siteName)
    {
        return $this->runCommand($siteName,'stop');
    }

    /**
     * @param $siteName
     * @see https://wiki.swoole.com/wiki/page/20.html
     */
    public function actionReload($siteName)
    {
        return $this->runCommand($siteName,'reload');
    }

    /**
     * execute command
     * @param $siteName
     * @param $command
     */
    private function runCommand($siteName,$command)
    {
        $cfg = $this->config[$siteName];
        $cfg['id'] = $siteName;
        $pidFile = Yii::getAlias($cfg['setting']['pid_file']);
        $masterPid     = file_exists($pidFile) ? file_get_contents($pidFile) : null;
        switch ($command){
            case 'start':
                print_r('please start swoole server through cli command: php start-script node start');
                break;
            case 'stop':
                if(!empty($masterPid)){
                    posix_kill($masterPid,SIGTERM);
                }else{
                    print_r('master pid is null, maybe you delete the pid file we created. you can manually kill the master process with signal SIGTERM.');
                }
                break;
            case 'reload':
                if (!empty($masterPid)) {
                    posix_kill($masterPid, SIGUSR1); // reload all worker
                    posix_kill($masterPid, SIGUSR2); // reload all task
                } else {
                    print_r('master pid is null, maybe you delete the pid file we created. you can manually kill the master process with signal SIGUSR1.');
                }
                break;
        }
    }
}