<?php

namespace Omnipost\Tests;

use Omnipost\Bridge\Symfony\OmnipostBundle;
use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Registry;
use Omnipost\YouTube\YouTubeProviderFactory;
use Omnisense\Contracts\Http\Data;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Omnipost outside Symfony: the harness's bare script (docker/harness/bin/bare)
 * run in a PHP process of its own - this one has loaded the bundle's tests -
 * builds the registry by hand, checks two posts against what each network
 * accepts, reads a channel and its videos through omnipost/youtube from the
 * answers kept in docker/harness/recorded/, and reports every class PHP
 * loaded on the way and every file since the autoloader (Composer requires
 * Twig's function files with it wherever Twig is installed, whatever the
 * script does). None may be a framework's.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation)|Symfony\\\\Bundle|Symfony\\\\Bridge|Doctrine|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|[a-z-]*bundle|[a-z-]*bridge)|doctrine|twig)/~';

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertContains(Registry::class, self::list($report['symbols']), 'the registry was built there');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        $installed = array_values(array_filter(array_column(self::list(require __DIR__.'/../docker/harness/plugins.php'), 1), static fn (mixed $factory): bool => \is_string($factory) && class_exists($factory)));
        foreach ($installed as $factory) {
            self::assertContains($factory, self::list($report['symbols']), 'every provider package installed, its factory built');
        }
        self::assertCount(\count($installed), self::list($report['factories']));
        self::assertCount(\count($installed), self::list($report['providers']), 'and its provider, with no token');
    }

    public function testAPostIsCheckedAgainstWhatEachNetworkAcceptsWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(InstagramProviderFactory::class)) {
            self::markTestSkipped('omnipost/instagram is not installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        $data = Data::of($report);
        self::assertSame(['platform' => 'instagram', 'publishes' => true, 'feed' => true], $data->get('providers.instagram'));
        self::assertSame([], $data->get('posts.a vertical reel of 42 seconds.instagram'));
        self::assertSame(['media[0].ratio: 16:9 is not accepted (accepted: 9:16, 4:5, 1:1).'], $data->get('posts.a landscape reel of twelve minutes.instagram'));
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    public function testAProviderAnswersFromRecordedAnswersWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(YouTubeProviderFactory::class)) {
            self::markTestSkipped('omnipost/youtube is not installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        $data = Data::of($report);
        self::assertTrue($report['recorded']);
        self::assertSame(['id' => 'UCabc', 'username' => '@claireviolin', 'name' => 'Claire', 'url' => 'https://www.youtube.com/@claireviolin', 'followers' => 4200, 'posts' => 36], $data->get('youtube.account'));
        $items = self::list($data->get('youtube.items'));
        self::assertSame(['shortA', 'longB'], array_column($items, 'id'));
        self::assertSame(['reel', 'video'], array_column($items, 'kind'), 'three minutes at most is a Short');
        self::assertSame([58, 3723], array_map(static fn (mixed $duration): int => \is_scalar($duration) ? (int) $duration : 0, array_column($items, 'duration')));
        self::assertSame('CAUQAA', $data->get('youtube.next'));
        self::assertSame([], $data->get('posts.a vertical reel of 42 seconds.youtube'), 'its variant gives the title');
        self::assertSame(['title: A title is required.'], $data->get('posts.a landscape reel of twelve minutes.youtube'));
        self::assertContains('Symfony\\Component\\HttpClient\\MockHttpClient', self::list($report['symbols']), 'the answers came through the HTTP client given');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNIPOST_AUTOLOAD"); $autoloaded = get_included_files(); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => array_values(array_diff(get_included_files(), $autoloaded))]);', '--', OmnipostBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework($report);
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $framework));
    }

    /**
     * @param array<mixed> $report its "symbols" and "files", lists of strings
     *
     * @return list<string> the classes, interfaces, traits and files of a framework among those loaded
     */
    private static function framework(array $report): array
    {
        $framework = [];
        foreach ([self::FRAMEWORK => 'symbols', self::FRAMEWORK_FILES => 'files'] as $pattern => $key) {
            foreach (self::list($report[$key] ?? null) as $name) {
                if (\is_string($name) && preg_match($pattern, $name)) {
                    $framework[] = $name;
                }
            }
        }

        return $framework;
    }

    /**
     * A list of the report, asserted to be one.
     *
     * @return array<mixed>
     */
    private static function list(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, array<mixed>} the exit status, the JSON printed
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIPOST_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
