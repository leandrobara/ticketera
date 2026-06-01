<?php

namespace App\Helpers;

class UrlHelper
{
    public static function normalize($url)
    {
        $normalizedUrl = [];
        // trim, lower and parse
        $parsedUrl = parse_url(strtolower(trim($url)));
        if (count($parsedUrl) > 1) {
            if (isset($parsedUrl['host'])) {
                // clean for www. and ///
                $normalizedUrl[] = self::cleanPath($parsedUrl['host']);
            }
            if (isset($parsedUrl['path'])) {
                $parsedUrl['path'] = self::cleanPath($parsedUrl['path']);
                $normalizedUrl = self::buildPath($parsedUrl['path'], $normalizedUrl);
            }
        } else {
            $pathArr = explode('/', $parsedUrl['path']);
            if (count($pathArr) > 1) {
                if ($pathArr[0]) {
                    $normalizedUrl[] = self::cleanPath($pathArr[0]);
                }
                $normalizedUrl = self::buildPath($pathArr, $normalizedUrl);
            } else {
                $normalizedUrl[] = self::cleanPath($pathArr[0]);
            }
        }

        return implode('', $normalizedUrl);
    }

    private static function cleanPath($path)
    {
        $path = str_replace('www.', '', $path);
        $path = preg_replace('/\/{2,}/', '/', $path);
        $path = preg_replace('/\/$/', '', $path);

        return $path;
    }

    private static function buildPath($path, $normalizedUrl)
    {
        if (!is_array($path)) {
            $path = explode('/', $path);
        }
        foreach ($path as $piece) {
            if ($piece) {
                if (!preg_match('/\./', $piece)) {
                    $normalizedUrl[] = '/' . $piece;
                }
            }
        }

        return $normalizedUrl;
    }
}
