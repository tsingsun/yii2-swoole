<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/17
 * Time: 下午4:11
 */

/**
 * 将反射方法替换为swoole协程方法
 */

namespace tsingsun\swoole {
    function call_user_func_array(...$params)
    {
        if (is_string($params[0])) {
            return \call_user_func_array(...$params);
        }
        return \Swoole\Coroutine::call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        if (is_string($params[0])) {
            return \call_user_func(...$params);
        }
        return \Swoole\Coroutine::call_user_func(...$params);
    }
}

namespace yii {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\base {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\behaviors {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\caching {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\captcha {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\console {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\console\controllers {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\data {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\db {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\di {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\filters {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\filters\auth {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\grid {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\helper {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\i18n {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\log {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\mail {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\mutex {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\rbac {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\validators {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\views {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\web {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\widgets {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

/**
 * 框架外支持
 */

namespace yii\gii {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\components {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\console {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\controllers {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\generators\controller {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\generators\crud {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\generators\extension {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\generators\form {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\generators\model {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace yii\gii\generators\module {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\bootstrap {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\db {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\di {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\helper {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\log {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\pool {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\redis {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}

namespace tsingsun\swoole\web {
    function call_user_func_array(...$params)
    {
        return \tsingsun\swoole\call_user_func_array(...$params);
    }

    function call_user_func(...$params)
    {
        return \tsingsun\swoole\call_user_func(...$params);
    }
}