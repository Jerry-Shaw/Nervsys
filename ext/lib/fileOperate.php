<?php
namespace ext\lib;
//file operation class
abstract class fileOperate
{
    /**
     * clean head '/' and foot '/',Win:change '\' to '/',
     * @param string $path
     * @return void
     */
    protected static function clean(string &$path):void
    {
        $path = strtr($path,['\\'=>'/']);

        stripos($path,'/') === 0 && $path = substr($path,1);

        strrpos($path,'/') == strlen($path) - 1 &&
        $path = substr_replace($path,'',strlen($path)-1,strlen($path)-1);
    }
    /**
     * get file mime
     * @param string $file
     * @return string
     */
    protected static function fileMime(string $file):string
    {
        $res = finfo_open(FILEINFO_MIME);
        $file = @finfo_file($res,$file);
        finfo_close($res);
        if (!$file) return '';
        return $file;
    }
}