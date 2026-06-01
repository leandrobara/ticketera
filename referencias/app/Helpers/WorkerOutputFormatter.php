<?php

namespace App\Helpers;

use Countable;
use JsonSerializable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class WorkerOutputFormatter
{

    private const INFO_STYLE = 'color:#243447;';
    private const ERROR_STYLE = 'color:#b71c1c;';
    private const MUTED_STYLE = 'color:#637381;';
    private const SUCCESS_STYLE = 'color:#1b5e20;';
    private const WARNING_STYLE = 'color:#b36b00;';
    private const SUMMARY_STYLE = 'cursor:pointer;font-weight:600;color:#243447;';
    private const SEPARATOR_HTML = "<br/><hr style='border-color:#e0e0e0;'/><br/>";
    private const PRE_STYLE =
        'background:#f8f9fa;padding:8px;border:1px solid #dfe3e8;border-radius:4px;' .
        'overflow:auto;max-height:460px;font-size:13px;line-height:1.4;'
    ;


    public static function heading(string $text, int $level = 3, array $options = []): void
    {
        $level = max(1, min(6, $level));
        $color = $options['color'] ?? '#243447';
        $marginTop = (int) ($options['margin_top'] ?? 16);
        $marginBottom = (int) ($options['margin_bottom'] ?? 6);
        $indent = max(0, (int) ($options['indent'] ?? 0)) * 24;

        $style = sprintf(
            'margin:%dpx 0 %dpx %dpx;color:%s;font-family:Arial,Helvetica,sans-serif;',
            $marginTop,
            $marginBottom,
            $indent,
            $color
        );

        echo sprintf('<h%d style="%s">%s</h%d>', $level, $style, self::escape($text), $level);
    }


    public static function message(string $message, string $type = 'info', array $options = []): void
    {
        $indent = max(0, (int) ($options['indent'] ?? 0)) * 24;
        $margin = $options['margin'] ?? '4px 0';

        $colorStyle = match ($type) {
            'success' => self::SUCCESS_STYLE,
            'error' => self::ERROR_STYLE,
            'warning' => self::WARNING_STYLE,
            'muted' => self::MUTED_STYLE,
            default => self::INFO_STYLE,
        };

        $styles = [
            sprintf('margin:%s;', $margin),
            $indent ? sprintf('margin-left:%dpx;', $indent) : null,
            'font-family:Arial,Helvetica,sans-serif;',
            'font-size:14px;',
            $colorStyle,
        ];

        echo sprintf(
            '<p style="%s">%s</p>',
            implode('', array_filter($styles)),
            self::escape($message)
        );
    }


    public static function separator(int $count = 1): void
    {
        for ($i = 0; $i < max(1, $count); $i++) {
            echo self::SEPARATOR_HTML;
        }
    }


    public static function data(string $title, mixed $data, array $options = []): void
    {
        $collapsed = $options['collapsed'] ?? true;
        $emptyMessage = $options['emptyMessage'] ?? 'No data';
        $indentLevel = max(0, (int) ($options['indent'] ?? 0));
        $count = null;

        [$normalized, $count] = self::normalizeData($data);
        $isEmpty = self::isEmpty($normalized);

        if ($isEmpty) {
            self::message($title . ': ' . $emptyMessage, $options['emptyType'] ?? 'muted', [
                'indent' => $indentLevel,
                'margin' => $options['emptyMargin'] ?? '4px 0',
            ]);
            return;
        }

        $summaryParts = array_filter([
            $title,
            is_null($count) ? null : '(' . $count . ')',
        ]);
        $summary = implode(' ', $summaryParts);
        $json = self::stringify($normalized);

        $containerMarginLeft = $indentLevel * 24;
        $containerStyle = sprintf(
            'margin:%s;font-family:Arial,Helvetica,sans-serif;',
            sprintf('6px 0 6px %dpx', $containerMarginLeft)
        );

        if ($collapsed) {
            echo sprintf('<details style="%s">', $containerStyle);
            echo sprintf('<summary style="%s">%s</summary>', self::SUMMARY_STYLE, self::escape($summary));
            echo sprintf('<pre style="%s">%s</pre>', self::PRE_STYLE, self::escape($json));
            echo '</details>';
            return;
        }

        echo sprintf('<div style="%s">', $containerStyle);
        echo sprintf(
            '<strong style="display:block;margin-bottom:4px;color:#243447;">%s</strong>', self::escape($summary)
        );
        echo sprintf('<pre style="%s">%s</pre>', self::PRE_STYLE, self::escape($json));
        echo '</div>';
    }


    private static function normalizeData(mixed $data): array
    {
        $count = null;

        if ($data instanceof Collection) {
            $count = $data->count();
            $data = $data->toArray();
        } elseif ($data instanceof Model) {
            $data = $data->toArray();
        } elseif ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        } elseif ($data instanceof \Stringable) {
            $data = (string) $data;
        } elseif ($data instanceof \DateTimeInterface) {
            $data = $data->format(\DateTimeInterface::ATOM);
        }

        if (is_array($data) || $data instanceof Countable) {
            $count = $count ?? (is_array($data) ? count($data) : count($data));
        }

        return [$data, $count];
    }


    private static function stringify(mixed $data): string
    {
        if (is_scalar($data) || $data === null) {
            return (string) (is_bool($data) ? ($data ? 'true' : 'false') : $data);
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            return $encoded;
        }

        return print_r($data, true);
    }


    private static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }


    private static function isEmpty(mixed $data): bool
    {
        if ($data === null) {
            return true;
        }
        if (is_string($data)) {
            return trim($data) === '';
        }
        if (is_array($data)) {
            return count($data) === 0;
        }
        if ($data instanceof Countable) {
            return count($data) === 0;
        }
        return false;
    }

}
