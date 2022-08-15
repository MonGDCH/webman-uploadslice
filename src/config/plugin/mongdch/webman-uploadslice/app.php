<?php

return [
    // 启用插件
    'enable' => true,
    // 允许上传的文件后缀
    'exts'      => [],
    // 分片文件大小限制
    'sliceSize' => 0,
    // 保存根路径
    'rootPath'  => public_path() . DIRECTORY_SEPARATOR . 'upload',
    // 临时文件存储路径，基于rootPath
    'tmpPath'   => 'tmp'
];
