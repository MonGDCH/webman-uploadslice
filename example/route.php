<?php

/**
 * 演示使用的上传接口DEMO
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */

use Webman\Route;
use support\Request;
use mon\util\Validate;
use mon\util\exception\UploadException;
use Mongdch\WebmanUploadslice\UploadSlice;

// 上传页面
Route::any('/', function (Request $request) {
    return view('upload');
});

// 上传接口
Route::post('/upload', function (Request $request) {
    $data = $request->post();
    // 验证数据
    $validate = new Validate();
    $check = $validate->data($data)->rule([
        'action'        => ['in:slice,merge'],
        'filename'      => ['required', 'str'],
        'chunk'         => ['int', 'min:0'],
        'chunkLength'   => ['required', 'int', 'min:0'],
        'uuid'          => ['required', 'str']
    ])->message([
        'action'        => 'action faild',
        'filename'      => 'filename faild',
        'chunk'         => 'chunk faild',
        'chunkLength'   => 'chunkLength faild',
        'uuid'          => 'uuid faild'
    ])->check();
    if (!$check) {
        return json(['code' => 0, 'msg' => $validate->getError()]);
    }
    // 验证上传分片必须的参数
    if ($request->post('action') == 'slice' && is_null($request->post('chunk'))) {
        return json(['code' => 0, 'msg' => 'chunk required']);
    }
    if ($request->post('action') == 'slice' && empty($request->file())) {
        return json(['code' => 0, 'msg' => 'upload faild']);
    }

    // 上传
    $sdk = new UploadSlice();
    $file = $request->file('file');
    try {
        if ($data['action'] == 'slice') {
            // 保存分片
            $saveInfo = $sdk->upload($data['uuid'], $file, $data['chunk']);
            return json(['code' => 1, 'msg' => 'ok', 'data' => $saveInfo]);
        }
        // 合并
        $mergeInfo = $sdk->merge($data['uuid'], $data['chunkLength'], $data['filename']);
        // $mergeInfo = $sdk->merge($data['uuid'], $data['chunkLength'], $data['filename'], 'dirname');
        return json(['code' => 1, 'msg' => 'ok', 'data' => $mergeInfo]);
    } catch (UploadException $e) {
        return json(['code' => 0, 'msg' => $e->getMessage()]);
    }


    return json($sdk->getConfig());
});

Route::fallback(function () {
    return json(['code' => 404, 'msg' => '404 not found']);
});

Route::disableDefaultRoute();
