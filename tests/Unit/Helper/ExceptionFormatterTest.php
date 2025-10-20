<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Helper\ExceptionFormatter;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionFormatterTest extends TestCase
{
    private ExceptionFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ExceptionFormatter('/var/www/app');
    }

    /**
     * Test basic exception formatting includes all required fields.
     */
    public function testFormatIncludesAllRequiredFields(): void
    {
        $exception = new RuntimeException('Test error', 123);

        $result = $this->formatter->format($exception);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('line', $result);
        $this->assertArrayHasKey('trace', $result);

        $this->assertSame(RuntimeException::class, $result['type']);
        $this->assertSame('Test error', $result['message']);
        $this->assertSame(123, $result['code']);
        $this->assertIsArray($result['trace']);
    }

    /**
     * Test that project root path is removed from file paths.
     */
    public function testFormatCleansProjectRootFromPaths(): void
    {
        $exception = new RuntimeException('Test');

        $result = $this->formatter->format($exception);

        // File path should not contain /var/www/app/
        $this->assertStringNotContainsString('/var/www/app/', $result['file']);

        // Each trace entry should also have clean paths
        foreach ($result['trace'] as $frame) {
            $this->assertStringNotContainsString('/var/www/app/', $frame);
        }
    }

    /**
     * Test trace limit restricts number of frames.
     */
    public function testFormatLimitsTraceDepth(): void
    {
        $exception = $this->createExceptionWithDeepTrace();

        $result = $this->formatter->format($exception, traceLimit: 3);

        $this->assertCount(4, $result['trace']); // 3 frames + "... (N more frames)" message
        $this->assertStringContainsString('more frames', $result['trace'][3]);
    }

    /**
     * Test that previous exception is included recursively.
     */
    public function testFormatIncludesPreviousException(): void
    {
        $previous = new Exception('Previous error', 100);
        $exception = new RuntimeException('Current error', 200, $previous);

        $result = $this->formatter->format($exception);

        $this->assertArrayHasKey('previous', $result);
        $this->assertIsArray($result['previous']);
        $this->assertSame(Exception::class, $result['previous']['type']);
        $this->assertSame('Previous error', $result['previous']['message']);
        $this->assertSame(100, $result['previous']['code']);
    }

    /**
     * Test that exception without previous does not have previous key.
     */
    public function testFormatWithoutPreviousException(): void
    {
        $exception = new RuntimeException('Test error');

        $result = $this->formatter->format($exception);

        $this->assertArrayNotHasKey('previous', $result);
    }

    /**
     * Test trace formatting includes function names.
     */
    public function testFormatIncludesFunctionNamesInTrace(): void
    {
        $exception = new RuntimeException('Test');

        $result = $this->formatter->format($exception);

        // At least one trace frame should contain a function/method call
        $hasFunction = false;
        foreach ($result['trace'] as $frame) {
            if (str_contains($frame, '::') || str_contains($frame, '->')) {
                $hasFunction = true;
                break;
            }
        }

        $this->assertTrue($hasFunction, 'Trace should include function/method names');
    }

    /**
     * Test trace format structure (file:line: function).
     */
    public function testTraceFormatStructure(): void
    {
        $exception = new RuntimeException('Test');

        $result = $this->formatter->format($exception);

        // Each trace frame (except the last "more frames") should match pattern: file(line): function
        foreach ($result['trace'] as $frame) {
            if (str_contains($frame, 'more frames')) {
                continue;
            }

            // Should contain parentheses for line number
            $this->assertStringContainsString('(', $frame);
            $this->assertStringContainsString(')', $frame);

            // Should contain colon separator
            $this->assertStringContainsString(':', $frame);
        }
    }

    /**
     * Test with very small trace limit (0 or 1).
     */
    public function testFormatWithMinimalTraceLimit(): void
    {
        $exception = new RuntimeException('Test');

        $result = $this->formatter->format($exception, traceLimit: 1);

        $this->assertLessThanOrEqual(2, count($result['trace'])); // 1 frame + optional "more frames"
    }

    /**
     * Test that multiple previous exceptions are handled (chained).
     */
    public function testFormatWithChainedPreviousExceptions(): void
    {
        $first = new Exception('First error', 100);
        $second = new RuntimeException('Second error', 200, $first);
        $third = new RuntimeException('Third error', 300, $second);

        $result = $this->formatter->format($third);

        // Check first level
        $this->assertSame('Third error', $result['message']);
        $this->assertArrayHasKey('previous', $result);

        // Check second level
        $this->assertSame('Second error', $result['previous']['message']);
        $this->assertArrayHasKey('previous', $result['previous']);

        // Check third level
        $this->assertSame('First error', $result['previous']['previous']['message']);
        $this->assertArrayNotHasKey('previous', $result['previous']['previous']);
    }

    /**
     * Helper to create exception with deep trace.
     */
    private function createExceptionWithDeepTrace(): RuntimeException
    {
        return $this->levelFive();
    }

    private function levelFive(): RuntimeException
    {
        return $this->levelFour();
    }

    private function levelFour(): RuntimeException
    {
        return $this->levelThree();
    }

    private function levelThree(): RuntimeException
    {
        return $this->levelTwo();
    }

    private function levelTwo(): RuntimeException
    {
        return $this->levelOne();
    }

    private function levelOne(): RuntimeException
    {
        return new RuntimeException('Deep trace exception');
    }
}
