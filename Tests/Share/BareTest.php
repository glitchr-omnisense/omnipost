<?php

namespace Omnishare\Tests;

use Omnishare\Registry;
use PHPUnit\Framework\TestCase;

/**
 * omnishare outside any framework: the harness's bare script
 * (docker/harness/bin/bare), in a PHP process of its own with Composer's
 * autoloader alone, shares a page to every network and reports what PHP
 * loaded for it, past what the autoloader loads by itself: omnishare's
 * classes, nothing else - in this harness as in an application's vendor.
 */
final class BareTest extends TestCase
{
    public function testEveryNetworkInPlainPhpLoadsNothingButOmnishare(): void
    {
        $report = self::bare();

        self::assertCount(13, $report['share']['links']);
        self::assertSame('https://bsky.app/intent/compose?text=Le%20Fuji%20%28l%27ascension%29%20%26%20nous%20%21%20%23japon%20https%3A%2F%2Fexample.com%2Farticles%2Ffuji', $report['share']['links']['bluesky']['url']);
        self::assertSame('Épingler sur Pinterest', $report['label']);
        self::assertContains(Registry::class, $report['loaded']);
        self::assertSame([], array_values((array) preg_grep('~^(?!Omnishare\\\\)~', $report['loaded'])), 'nothing but omnishare');
    }

    /** @return array{share: array{links: array<string, array{url: string}>}, label: string, loaded: list<string>} */
    private static function bare(): array
    {
        // The autoloader that knows omnishare: not necessarily the one that
        // loaded Composer's ClassLoader first (an application's tools may have their own).
        $autoload = null;
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile(Registry::class)) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        if (null === $autoload) {
            self::markTestSkipped('No autoloader knows omnishare.');
        }
        $process = proc_open([\PHP_BINARY, __DIR__.'/../docker/harness/bin/bare'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNISHARE_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        /** @var array{share: array{links: array<string, array{url: string}>}, label: string, loaded: list<string>} $report */
        return $report;
    }
}
