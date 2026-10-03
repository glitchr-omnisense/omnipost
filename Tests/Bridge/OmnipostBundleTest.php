<?php

namespace Omnipost\Tests\Bridge;

use Omnipost\Bridge\Symfony\OmnipostBundle;
use Omnipost\Exception\InvalidConfigException;
use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Model\Media;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\ProviderInterface;
use Omnipost\PublisherInterface;
use Omnipost\Registry;
use Omnipost\Tests\StubFactory;
use Omnipost\Validator;
use Omnipost\YouTube\YouTubeProviderFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;

final class OmnipostBundleTest extends TestCase
{
    private ?OmnipostTestKernel $kernel = null;

    protected function tearDown(): void
    {
        if (null !== $this->kernel) {
            $dir = $this->kernel->getProjectDir();
            $this->kernel->shutdown();
            $this->kernel = null;
            self::remove($dir);
        }
        restore_exception_handler();
    }

    public function testTheKernelBootsAndTheProvidersAreInjectableByName(): void
    {
        $container = $this->boot([
            'fediverse' => ['factory' => 'stub', 'options' => ['token' => 'f']],
        ]);

        $registry = $container->get(Registry::class);
        self::assertSame(['fediverse'], $registry->names());

        $site = $container->get(Site::class);
        self::assertSame($registry, $site->registry, 'the registry autowired');
        self::assertInstanceOf(Validator::class, $site->validator);
        self::assertSame($registry->get('fediverse'), $site->fediverse, 'injected by its name, as what it does');
        self::assertSame('post_f_1', $site->fediverse->publish(Post::reel(Media::video('https://site.example/reel.mp4')))->id);
        self::assertSame($registry->get('fediverse'), $container->get(Wall::class)->fediverse, 'and as a provider');
    }

    public function testAProviderWithNoTokenBreaksNothingUntilACallNeedsIt(): void
    {
        if (!class_exists(InstagramProviderFactory::class) || !class_exists(YouTubeProviderFactory::class)) {
            self::markTestSkipped('omnipost/instagram and omnipost/youtube are not installed.');
        }
        // The README's configuration, no variable set in the environment.
        $container = $this->boot([
            'instagram' => ['factory' => 'instagram', 'options' => ['access_token' => '%env(default::INSTAGRAM_ACCESS_TOKEN)%', 'app_id' => '%env(default::INSTAGRAM_APP_ID)%', 'app_secret' => '%env(default::INSTAGRAM_APP_SECRET)%']],
            'youtube' => ['factory' => 'youtube', 'options' => ['api_key' => '%env(default::YOUTUBE_API_KEY)%', 'channel_id' => '%env(default::YOUTUBE_CHANNEL_ID)%']],
        ]);

        $registry = $container->get(Registry::class);
        self::assertTrue($registry->has('instagram'));
        self::assertSame(['instagram', 'youtube', 'fediverse'], $registry->names());
        $factories = $registry->factories();
        sort($factories);
        self::assertSame(['instagram', 'stub', 'youtube'], $factories, 'the packages installed, and the application\'s own');

        $instagram = $registry->get('instagram');
        self::assertInstanceOf(PublisherInterface::class, $instagram);
        self::assertTrue($instagram->capabilities()->supports(PostKind::REEL), 'what needs no token works');
        self::assertCount(3, $registry->publishers());
        self::assertCount(2, $registry->feeds());

        try {
            $instagram->account();
            self::fail('No token: the call must say so.');
        } catch (InvalidConfigException $e) {
            self::assertSame('The "instagram" provider needs: access_token.', $e->getMessage());
        }
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "youtube" provider needs: api_key');
        $registry->get('youtube')->account();
    }

    /** @param array<string, array{factory: string, options?: array<string, mixed>}> $providers */
    private function boot(array $providers): \Symfony\Component\DependencyInjection\ContainerInterface
    {
        $providers += ['fediverse' => ['factory' => 'stub', 'options' => ['token' => 'f']]];
        $this->kernel = new OmnipostTestKernel($providers);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }

    private static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}

/** An application of one bundle, its configuration as the YAML would give it. */
final class OmnipostTestKernel extends Kernel
{
    private readonly string $dir;

    /** @param array<string, array{factory: string, options?: array<string, mixed>}> $providers */
    public function __construct(private readonly array $providers)
    {
        $this->dir = sys_get_temp_dir().'/omnipost-'.bin2hex(random_bytes(6));
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new OmnipostBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('omnipost', ['providers' => $this->providers]);
            $container->register('http_client', MockHttpClient::class);
            // An application's own provider, autoconfigured.
            $container->register(StubFactory::class)->setAutoconfigured(true);
            $container->register(Site::class)->setAutowired(true)->setPublic(true);
            $container->register(Wall::class)->setAutowired(true)->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }
}

final class Site
{
    public function __construct(
        public readonly Registry $registry,
        public readonly Validator $validator,
        public readonly PublisherInterface $fediverse,
    ) {
    }
}

final class Wall
{
    public function __construct(public readonly ProviderInterface $fediverse)
    {
    }
}
