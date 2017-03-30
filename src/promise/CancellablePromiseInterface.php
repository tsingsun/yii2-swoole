<?php

namespace yii\swoole\promise;

interface CancellablePromiseInterface extends PromiseInterface
{
    /**
     * @return void
     */
    public function cancel();
}
