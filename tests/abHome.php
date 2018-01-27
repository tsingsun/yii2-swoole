<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/26
 * Time: 下午5:46
 */

$i = 1000;
while ($i>0){
    $ch = curl_init('http://localhost:9501');
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    curl_close($ch);
    $i--;
    echo $i.' ';
}