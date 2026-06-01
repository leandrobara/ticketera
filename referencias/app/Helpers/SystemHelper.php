<?php

namespace App\Helpers;


class SystemHelper
{

    public static function setTimeLimit(int $timeLimit): void
    {
        set_time_limit($timeLimit);
        ini_set('max_execution_time', "$timeLimit");
    }


    public static function displayAllErrors(): void
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }


    public static function setMemoryLimitMB(int $limitInMB): void
    {
        ini_set('memory_limit', "{$limitInMB}M");
    }


    public static function setVarDumpMaxDepth(int $depth): void
    {
        ini_set('xdebug.var_display_max_depth', $depth);
    }


    public static function setManualFlush(): void
    {
        ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_flush();
        }
        ob_implicit_flush(1);
    }


    public static function doFlush(): void
    {
        flush();
        @ob_flush();
    }


    public static function setBinaryDownloadHeaders(string $filename, int $size)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        // header('Content-Disposition: attachment; filename=' . $filename);
        $contentDispositionHeader = 'Content-Disposition: attachment; ';
        $contentDispositionHeader .= 'filename="' . basename($filename) . '"; ';
        $contentDispositionHeader .= 'filename*=UTF-8\'\'' . rawurlencode($filename);
        header($contentDispositionHeader);
    }

}
