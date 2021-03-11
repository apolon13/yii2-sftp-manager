<?php

namespace Apolon\sftp;

use phpseclib\Net\SFTP;
use yii\base\Component;

define('NET_SFTP_LOGGING', 'NET_SFTP_LOG_COMPLEX');

/**
 * Class SFtpManager
 * @mixin SFTP
 * @package Apolon
 */
class SFtpManager extends Component
{
    /**
     * Type dir
     */
    const TYPE_DIR = 2;

    /**
     * Type file
     */
    const TYPE_FILE = 1;

    /**
     * @var array
     */
    public $settings = [
        "port" => 22,
        "timeout" => 10
    ];

    /**
     * @var string
     */
    public $host;

    /**
     * @var array
     */
    public $elements = [];

    /**
     * @var SFTP
     */
    protected $connect;

    /**
     * @param $host
     * @param $login
     * @param $pass
     * @return bool
     */
    public function connect($host, $login, $pass)
    {
        $this->host = $host;
        $this->connect = new SFTP($this->host, $this->settings['port'], $this->settings['timeout']);
        return $this->connect->login($login, $pass);
    }


    /**
     * @param string $dir
     * @param bool $recursive
     * @return array
     */
    public function scanDir($dir = ".", $recursive = false)
    {
        $this->elements = [];
        $list = $this->connect->rawlist($dir, $recursive);
        usort($list, function ($attr) {
            return ($attr["type"] == self::TYPE_DIR) ? false : true;
        });
        foreach ($list as $element => $attr) {
            if ($attr['filename'] != '.' && $attr['filename'] != '..') {
                array_push($this->elements, (object)$attr);
            }
        }
        return $this->elements;
    }

    /**
     * @param $name
     * @param $param
     * @return mixed
     */
    public function __call($name, $param)
    {
        if (method_exists($this->connect, $name)) {
            if (!empty($param) && is_array($param)) {
                return call_user_func_array([$this->connect, $name], $param);
            }
            return $this->connect->$name();
        }
        return parent::__call($name, $param);
    }

    /**
     * @return array
     */
    public function getFolders()
    {
        return $this->getByProp('type', self::TYPE_DIR);
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->getByProp('type', self::TYPE_FILE);
    }

    /**
     * @param $index
     * @param $value
     * @return array
     */
    protected function getByProp($index, $value)
    {
        $elements = [];
        foreach ($this->elements as $element) {
            if ($element->$index == $value)
                array_push($elements, $element);
        }
        return $elements;
    }

    /**
     * @param $archiveName
     * @param $remotePathElement
     * @return \stdClass
     * @throws \Exception
     */
    public function createTarArchive($archiveName, $remotePathElement)
    {
        $archive = new \stdClass();
        if (!strripos($archiveName, ".tar")) {
            throw new \Exception("extension archive should be .tar");
        }
        $archive->name = $archiveName;
        $archive->path = $this->realpath("./") . "/{$archiveName}";
        $this->execCommand("tar -cf $archive->name $remotePathElement");
        return $archive;
    }

    /**
     * @param $command
     * @return string
     */
    public function execCommand($command)
    {
        return $this->connect->exec($command);
    }

    /**
     * @param $remoteFileName
     * @param $localFileName
     * @return bool|mixed
     */
    public function saveFile($remoteFileName, $localFileName)
    {
        if ($this->connect->is_file($remoteFileName)) {
            return $this->connect->get($remoteFileName, $localFileName);
        }
        return false;
    }

}


