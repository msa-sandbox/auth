<?php

declare(strict_types=1);

namespace App\Helper;

use Throwable;

/**
 * Helper to format exceptions into structured arrays for logging.
 *
 * Converts exception to array with clean paths and limited trace depth.
 */
readonly class ExceptionFormatter
{
    public function __construct(
        private string $projectRoot,
    ) {
    }

    /**
     * Format exception into structured array.
     *
     * @param Throwable $exception Exception to format
     * @param int $traceLimit Maximum number of trace frames to include
     *
     * @return array Formatted exception data
     */
    public function format(Throwable $exception, int $traceLimit = 10): array
    {
        $result = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $this->cleanPath($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception->getTrace(), $traceLimit),
        ];

        // Include previous exception if exists (recursive)
        if ($previous = $exception->getPrevious()) {
            $result['previous'] = $this->format($previous, $traceLimit);
        }

        return $result;
    }

    /**
     * Format stack trace into readable array of strings.
     *
     * @param array $trace Raw stack trace from exception
     * @param int $limit Maximum number of frames to include
     *
     * @return array Formatted trace frames
     */
    private function formatTrace(array $trace, int $limit): array
    {
        $formatted = [];
        $count = 0;

        foreach ($trace as $item) {
            if ($count >= $limit) {
                $remaining = count($trace) - $limit;
                $formatted[] = sprintf('... (%d more frames)', $remaining);
                break;
            }

            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? 0;
            $class = $item['class'] ?? '';
            $type = $item['type'] ?? '';
            $function = $item['function'] ?? '';

            $formatted[] = sprintf(
                '%s(%d): %s%s%s',
                $this->cleanPath($file),
                $line,
                $class,
                $type,
                $function
            );

            ++$count;
        }

        return $formatted;
    }

    /**
     * Clean file path by removing project root prefix.
     *
     * @param string $path Absolute file path
     *
     * @return string Relative path from project root
     */
    private function cleanPath(string $path): string
    {
        return str_replace($this->projectRoot.'/', '', $path);
    }
}
