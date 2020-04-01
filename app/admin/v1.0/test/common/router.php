<?php
return [
    'PATH' => 'index.php',
    '/' => [
        'ctrl' => 'index',
        'act' => 'index1',
    ],
    '/doc' => [
        'ctrl' => 'index',
        'act' => 'document',
    ],
    '/index' => [
        'ctrl' => 'index',
        'act' => '*', //*会替换为 pathinfo 的 action
    ],
    '/model' => [
        'ctrl' => 'model',
        'act' => '*', //*会替换为 pathinfo 的 action
    ],
    '*' => [ // 以上匹配不到时使用此路由
        'ctrl' => 'index',
        'act' => '_404',
    ],
];
