<?php

namespace App\Helpers;

use Illuminate\Support\Str;


class StringHelper
{

    private function slugify(string $text, string $separator = '_'): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', $separator, $text);
        $text = trim($text, $separator);
        return $text;
    }


    public function tokenizeEmail(string $email): array
    {
        if (stripos($email, '@') === false) {
            return [];
        }

        $email = trim(strtolower($email));
        if (!$email) {
            return [];
        }

        $firstPart = Str::before($email, '@');

        $firstPartWithSpaces = str_replace(['.', '_', '-'], [' ', ' ', ' '], $firstPart);
        $partials = explode(' ', $firstPartWithSpaces);
        $partials[] = $firstPart;
        
        $firstPartWithoutSymbols = str_replace(['.', '_', '-'], '', $firstPart);
        // $partials = array_merge($partials, $this->tokenizeString($firstPartWithoutSymbols));
        
        $partials = collect($partials)->filter()->unique()->values()->toArray();
        return $partials;
    }


    public function tokenizePhone(string $phone): array
    {
        $phone = trim(strtolower($phone));
        if (!$phone) {
            return [];
        }

        $phoneWithSpaces = preg_replace("/[^0-9]/", ' ', $phone);
        $parts = explode(' ', $phoneWithSpaces);
        $partials = collect($parts)->filter(function ($part) {
            return trim($part) && strlen($part) > 3;
        })->unique()->values()->toArray();

        return $partials;
    }


    public function tokenizeString(string $word, int $gramSize = 6): array
    {
        $word = trim(strtolower($word));
        if (!$word) {
            return [];
        }

        $allGrams = [];
        for ($i = 0; $i < strlen($word); $i++) {
            $partialWord = mb_substr($word, $i);
            $grams = str_split($partialWord, $gramSize);
            $allGrams = array_merge($allGrams, $grams);
        }
        return collect($allGrams)->filter(function ($part) use ($gramSize) {
            return strlen($part) == $gramSize;
        })->unique()->values()->toArray();
    }


    public function removeLineBreaks(string $text): string
    {
        $text = preg_replace('/\r\n|\r|\n/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text;
    }


    public function removeEmojis(string $string): string
    {
        // Match Emoticons
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clearedString = preg_replace($regexEmoticons, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clearedString = preg_replace($regex_symbols, '', $clearedString);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clearedString = preg_replace($regex_transport, '', $clearedString);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clearedString = preg_replace($regex_misc, '', $clearedString);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        $clearedString = preg_replace($regex_dingbats, '', $clearedString);

        return trim($clearedString);
    }


    public function convertArrayFieldsToString(array $arr): array
    {
        $convertedArr = [];
        foreach ($arr as $i => $row) {
            if (is_array($row)) {
                $convertedArr[$i] = $this->convertArrayFieldsToString($row);
            } else {
                $convertedArr[$i] = trim(strval($row));
            }
        }
        return $convertedArr;
    }

}
