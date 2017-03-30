<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/7
 * Time: 上午10:21
 */

namespace yii\swoole\helper;


use yii\swoole\promise\Coroutine;
use yii\swoole\promise\FulfilledPromise;
use yii\swoole\promise\Promise;

class AsyncHelper
{
    /**
     * @param string $url
     * @return array
     */
    static function dnsLookup($url) {
        $remote = AsyncHelper::parseRemoteInfo($url);
        $dns = Promise\promisify('swoole_async_dns_lookup',
            function($promise) {
                    swoole_event_wait();
                });
        return $dns($remote['host'])->wait();
//        $pr = Promise\coroutine(function ()use($remote){
//            $dns = Promise\promisify('swoole_async_dns_lookup');
////                function($promise) {
////                    swoole_event_wait();
////                });
//            yield $dns($remote['host']);
//        });
//        return $pr->wait();
    }

    /**
     * @param string $url
     * @return array
     */
    static function parseRemoteInfo($url){
        $url = strtolower($url);
        $remote = [
            'scheme'=>'http',
            'port'=>'80',
            'path'=>'/',
        ];
        if(strpos($url,'http')!==0){
            $url = 'http://'.$url;
        }
        $remote = array_merge($remote,parse_url($url));
        $schema = strtolower($remote['schema']??'http');
        if(!isset($remote['port']) && $schema =='https'){
            $remote['port'] = 443;
        }
        return $remote;
    }
}