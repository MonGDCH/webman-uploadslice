<?php

namespace Mongdch\WebmanUploadslice;

use mon\util\File;
use Webman\File as WebmanFile;
use mon\util\exception\UploadException;

/**
 * 大文件分片上传
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class UploadSlice
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 允许上传的文件后缀
        'exts'      => [],
        // 分片文件大小限制
        'sliceSize' => 0,
        // 保存根路径
        'rootPath'  => '',
        // 临时文件存储路径，基于rootPath
        'tmpPath'   => 'tmp'
    ];

    /**
     * 错误的分片序号
     *
     * @var array
     */
    protected $error_chunk = [];

    /**
     * 构造方法
     *
     * @param array $config 自定义配置信息
     */
    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config = config('plugin.mongdch.webman-uploadslice.app');
        }
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取配置信息
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 设置配置信息
     *
     * @param array|string $config  配置信息或配置节点  
     * @param mixed $value 值
     * @return UploadSlice
     */
    public function setConifg($config, $value = null): UploadSlice
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        } else {
            $this->config[$config] = $value;
        }

        return $this;
    }

    /**
     * 获取错误的分片序号
     *
     * @return array
     */
    public function getErrorChunk(): array
    {
        return $this->error_chunk;
    }

    /**
     * 保存上传的文件分片到临时文件目录
     *
     * @param string $fileID 文件唯一ID
     * @param WebmanFile $file 文件流对象
     * @param integer $chunk 文件分片序号，从0递增到N
     * @throws UploadException
     * @return false|array 文件保存路径
     */
    public function upload(string $fileID, WebmanFile $file, int $chunk = 0)
    {
        // 检测上传保存路径
        if (!$this->checkPath()) {
            return false;
        }

        // 校验分片文件大小
        if ($this->config['sliceSize'] > 0 && $file->getSize() > $this->config['sliceSize']) {
            throw new UploadException('分片文件大小不符', UploadException::ERROR_UPLOAD_SIZE_FAILD);
        }
        // 保存临时文件
        $fileName = md5($fileID) . '_' . $chunk;
        $tmpPath = $this->config['rootPath'] . DIRECTORY_SEPARATOR . $this->config['tmpPath'] . DIRECTORY_SEPARATOR . $fileID;
        if (!File::instance()->createDir($tmpPath)) {
            throw new UploadException('创建临时文件存储目录失败', UploadException::ERROR_UPLOAD_DIR_NOT_FOUND);
        }
        $savePath = $tmpPath . DIRECTORY_SEPARATOR . $fileName;
        $file->move($savePath);

        return ['savePath' => $savePath, 'saveDir' => $tmpPath, 'fileName' => $fileName];
    }

    /**
     * 合并分片临时文件，生成上传文件
     *
     * @param string $fileID    文件唯一ID
     * @param integer $chunkLength  文件分片长度
     * @param string $fileName  保存文件名
     * @param string $saveDir   基于 rootPath 路径下的多级目录存储路径
     * @throws UploadException
     * @return array 文件保存路径
     */
    public function merge(string $fileID, int $chunkLength, string $fileName, string $saveDir = ''): array
    {
        // 分片临时文件存储目录
        $tmpPath = $this->config['rootPath'] . DIRECTORY_SEPARATOR . $this->config['tmpPath'] . DIRECTORY_SEPARATOR . $fileID;
        if (!is_dir($tmpPath)) {
            throw new UploadException('临时文件不存在', UploadException::ERROR_UPLOAD_DIR_NOT_FOUND);
        }
        // 验证文件名
        $ext = File::instance()->getExt($fileName);
        if (!empty($this->config['exts']) && !in_array($ext, $this->config['exts'])) {
            throw new UploadException('不支持文件保存类型', UploadException::ERROR_UPLOAD_EXT_FAILD);
        }
        // 多级目录存储
        $savePath = $this->config['rootPath'] . DIRECTORY_SEPARATOR . $saveDir;
        if (!empty($saveDir) && !is_dir($savePath)) {
            if (!File::instance()->createDir($savePath)) {
                throw new UploadException('创建文件存储目录失败', UploadException::ERROR_UPLOAD_DIR_NOT_FOUND);
            }
        }
        // 验证分片文件完整性
        $this->error_chunk = [];
        $chunkName = md5($fileID);
        for ($i = 0; $i < $chunkLength; $i++) {
            $checkName = $chunkName . '_' . $i;
            $chunkPath = $tmpPath . DIRECTORY_SEPARATOR . $checkName;
            if (!file_exists($chunkPath)) {
                $this->error_chunk[] = $i;
                throw new UploadException('分片文件不完整', UploadException::ERROR_CHUNK_FAILD);
            }
        }
        // 合并文件
        $saveFile = $savePath . DIRECTORY_SEPARATOR . $fileName;
        // 打开保存文件句柄
        $writerFp = fopen($saveFile, "ab");
        for ($k = 0; $k < $chunkLength; $k++) {
            $checkName = $chunkName . '_' . $k;
            $chunkPath = $tmpPath . DIRECTORY_SEPARATOR . $checkName;
            // 读取临时文件
            $readerFp = fopen($chunkPath, "rb");
            // 写入
            fwrite($writerFp, fread($readerFp, filesize($chunkPath)));
            // 关闭句柄
            fclose($readerFp);
            unset($readerFp);
            // 删除临时文件
            File::instance()->removeFile($chunkPath);
        }
        // 关闭保存文件句柄
        fclose($writerFp);
        // 删除临时目录
        File::instance()->removeDir($tmpPath);

        return ['savePath' => $saveFile, 'saveDir' => $savePath, 'fileName' => $fileName];
    }

    /**
     * 检测上传根目录
     *
     * @throws UploadException
     * @return boolean
     */
    protected function checkPath()
    {
        $rootPath = $this->config['rootPath'];
        if ((!is_dir($rootPath) && !File::instance()->createDir($rootPath)) || (is_dir($rootPath) && !is_writable($rootPath))) {
            throw new UploadException('上传文件保存目录不可写入：' . $rootPath, UploadException::ERROR_UPLOAD_DIR_NOT_FOUND);
        }
        $tmpPath = $rootPath . DIRECTORY_SEPARATOR . $this->config['tmpPath'];
        if ((!is_dir($tmpPath) && !File::instance()->createDir($tmpPath)) || (is_dir($tmpPath) && !is_writable($tmpPath))) {
            throw new UploadException('上传文件临时保存目录不可写入：' . $tmpPath, UploadException::ERROR_UPLOAD_DIR_NOT_FOUND);
        }
        return true;
    }
}
