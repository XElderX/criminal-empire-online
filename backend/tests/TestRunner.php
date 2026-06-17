<?php

namespace Tests;

use RuntimeException;
use Throwable;

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo "[PASS] {$name}\n";
        } catch (SkippedTest $exception) {
            $this->skipped++;
            echo "[SKIP] {$name}: {$exception->getMessage()}\n";
        } catch (Throwable $exception) {
            $this->failed++;
            echo "[FAIL] {$name}: {$exception->getMessage()}\n";
        }
    }

    public function assertTrue(bool $condition, string $message = 'Expected true.'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function assertFalse(bool $condition, string $message = 'Expected false.'): void
    {
        $this->assertTrue(!$condition, $message);
    }

    public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $details = $message !== '' ? $message . ' ' : '';
            $details .= 'Expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true) . '.';

            throw new RuntimeException($details);
        }
    }

    public function assertGreaterThan(
        int|float $minimum,
        int|float $actual,
        string $message = ''
    ): void {
        if ($actual <= $minimum) {
            throw new RuntimeException(
                $message !== ''
                    ? $message
                    : "Expected {$actual} to be greater than {$minimum}."
            );
        }
    }

    public function assertLessThan(
        int|float $maximum,
        int|float $actual,
        string $message = ''
    ): void {
        if ($actual >= $maximum) {
            throw new RuntimeException(
                $message !== ''
                    ? $message
                    : "Expected {$actual} to be less than {$maximum}."
            );
        }
    }

    public function assertContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException("Expected content to contain: {$needle}");
        }
    }

    public function skip(string $reason): never
    {
        throw new SkippedTest($reason);
    }

    public function finish(): int
    {
        echo "\nResults: {$this->passed} passed, {$this->failed} failed, "
            . "{$this->skipped} skipped.\n";

        return $this->failed === 0 ? 0 : 1;
    }
}

final class SkippedTest extends RuntimeException
{
}
