<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/6
 * Time: 下午2:43
 */

namespace yii\swoole\process;


class Process
{
    public $mpid=0;
    public $works=[];
    public $max_precess=1;
    public $new_index=0;

    public function __construct(){
        try {
            swoole_set_process_name(sprintf('php-ps:%s', 'master'));
            $this->mpid = posix_getpid();
            $this->run();
            $this->processWait();
        }catch (\Exception $e){
            die('ALL ERROR: '.$e->getMessage());
        }
    }

    public function run(){
        for ($i=0; $i < $this->max_precess; $i++) {
            $this->CreateProcess();
        }
    }

    public function CreateProcess($index=null){
        $process = new \Swoole\Process(function(\Swoole\Process $worker)use($index){
            if(is_null($index)){
                $index=$this->new_index;
                $this->new_index++;
            }
            swoole_set_process_name(sprintf('php-ps:%s',$index));
            for ($j = 0; $j < 16000; $j++) {
                $this->checkMpid($worker);
                echo "msg: {$j}\n";
                sleep(1);
            }
        }, false, false);
        $pid=$process->start();
        $this->works[$index]=$pid;
        return $pid;
    }
    public function checkMpid(&$worker){
        if(!\Swoole\Process::kill($this->mpid,0)){
            $worker->exit();
            // 这句提示,实际是看不到的.需要写到日志中
            echo "Master process exited, I [{$worker['pid']}] also quit\n";
        }
    }

    public function rebootProcess($ret){
        $pid=$ret['pid'];
        $index=array_search($pid, $this->works);
        if($index!==false){
            $index=intval($index);
            $new_pid=$this->CreateProcess($index);
            echo "rebootProcess: {$index}={$new_pid} Done\n";
            return;
        }
        throw new \Exception('rebootProcess Error: no pid');
    }

    public function processWait(){
        while(1) {
            if(count($this->works)){
                $ret = \Swoole\Process::wait();
                if ($ret) {
                    $this->rebootProcess($ret);
                }
            }else{
                break;
            }
        }
    }
}