<?php

// Don't redefine the functions if included multiple times.
if (!function_exists('yii\swoole\promise\promise_for')) {
    require __DIR__ . '/promise/functions.php';
}
