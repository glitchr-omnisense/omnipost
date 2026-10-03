<?php

namespace Omnipost\Harness;

use Omnipost\Auth\OAuthInterface;
use Omnipost\Auth\RefreshableInterface;
use Omnipost\Exception\InvalidConfigException;
use Omnipost\Exception\InvalidPostException;
use Omnipost\Exception\OmnipostException;
use Omnipost\FeedInterface;
use Omnipost\Model\Media;
use Omnipost\Model\MediaKind;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Variant;
use Omnipost\ProviderFactoryInterface;
use Omnipost\ProviderInterface;
use Omnipost\PublisherInterface;
use Omnipost\Registry;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The console that exercises every provider with the tokens in .env:
 * providers, capabilities, account, feed, validate, publish, status,
 * refresh, authorize, exchange. Results are printed whole, as JSON.
 */
final class Console
{
    /** @var array<string, array{factory: string, needs: array{read: list<list<string>>, publish: list<list<string>>}, options: array<string, mixed>}> */
    private array $config;

    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    private Registry $registry;

    private HttpClientInterface $http;

    private function __construct()
    {
        $this->config = require __DIR__.'/../config/providers.php';
        $this->http = HttpClient::create();
        foreach (require __DIR__.'/../plugins.php' as [, $class]) {
            if (class_exists($class)) {
                $factory = new $class($this->http);
                $this->factories[$factory->getName()] = $factory;
            }
        }
        // Every installed provider, configured or not: what needs no token works without one.
        $installed = array_filter($this->config, fn (array $p) => isset($this->factories[$p['factory']]));
        $this->registry = new Registry($this->factories, array_map(static fn (array $p) => ['factory' => $p['factory'], 'options' => $p['options']], $installed));
    }

    public static function create(): Application
    {
        $self = new self();
        $provider = new InputArgument('provider', InputArgument::REQUIRED, 'instagram, youtube');
        $media = new InputArgument('media', InputArgument::REQUIRED, 'The picture\'s or video\'s public URL (or a local file, for YouTube)');
        $caption = new InputArgument('caption', InputArgument::OPTIONAL, 'The caption', '');
        $post = [
            new InputOption('title', null, InputOption::VALUE_REQUIRED, 'A title (YouTube requires one)'),
            new InputOption('kind', null, InputOption::VALUE_REQUIRED, 'reel, image, video, story (by the file\'s extension otherwise)'),
            new InputOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A hashtag, without the #'),
            new InputOption('file', null, InputOption::VALUE_REQUIRED, 'The local file, for the providers that upload (YouTube)'),
            new InputOption('cover', null, InputOption::VALUE_REQUIRED, 'A cover\'s public URL, for a reel'),
            new InputOption('duration', null, InputOption::VALUE_REQUIRED, 'The video\'s duration in seconds, for the validator'),
            new InputOption('width', null, InputOption::VALUE_REQUIRED, 'In pixels, for the validator'),
            new InputOption('height', null, InputOption::VALUE_REQUIRED, 'In pixels, for the validator'),
            new InputOption('option', 'o', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A provider option: "privacy=unlisted", "share_to_feed=0"'),
        ];
        $app = new Application('omnipost', '1.x');
        $app->addCommand($self->command('providers', 'Which providers are installed, and what each can do with the variables in .env', [], fn ($in, $out) => $self->providers($out)));
        $app->addCommand($self->command('capabilities', 'What the network accepts (no token needed)', [$provider], fn ($in, $out) => $self->print($out, $self->publisher($in)->capabilities())));
        $app->addCommand($self->command('account', 'The connected account', [$provider], fn ($in, $out) => $self->print($out, $self->feeder($in)->account())));
        $app->addCommand($self->command('feed', 'The account\'s posts, newest first', [$provider, new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, '', '10'), new InputOption('cursor', 'c', InputOption::VALUE_REQUIRED, 'The previous page\'s "next"')], fn ($in, $out) => $self->print($out, $self->feeder($in)->feed($in->getOption('cursor'), (int) $in->getOption('limit')))));
        $app->addCommand($self->command('validate', 'The violations a post would meet (nothing is sent)', [$provider, $media, $caption, ...$post], fn ($in, $out) => $self->validate($in, $out)));
        $app->addCommand($self->command('publish', 'A post published, then followed until it is (or fails)', [$provider, $media, $caption, ...$post, new InputOption('wait', 'w', InputOption::VALUE_REQUIRED, 'Seconds to follow it', '600')], fn ($in, $out) => $self->publish($in, $out)));
        $app->addCommand($self->command('status', 'Where a publication stands', [$provider, new InputArgument('id', InputArgument::REQUIRED, 'The publication\'s id (Instagram: the container\'s or the media\'s)')], fn ($in, $out) => $self->print($out, $self->publisher($in)->status($in->getArgument('id')))));
        $app->addCommand($self->command('refresh', 'A fresh token, printed as a site would store it', [$provider], fn ($in, $out) => $self->print($out, $self->refreshable($in)->refresh()->toArray())));
        $app->addCommand($self->command('authorize', 'The URL to connect the account', [$provider, new InputArgument('redirect-uri', InputArgument::REQUIRED), new InputOption('state', null, InputOption::VALUE_REQUIRED, '', 'omnipost')], fn ($in, $out) => $out->writeln($self->oauth($in)->authorizationUrl($in->getArgument('redirect-uri'), $in->getOption('state')))));
        $app->addCommand($self->command('exchange', 'The code the redirect URI received, for a token', [$provider, new InputArgument('code', InputArgument::REQUIRED), new InputArgument('redirect-uri', InputArgument::REQUIRED)], fn ($in, $out) => $self->print($out, $self->oauth($in)->exchange($in->getArgument('code'), $in->getArgument('redirect-uri'))->toArray())));

        return $app;
    }

    /** @param list<InputArgument|InputOption> $definition */
    private function command(string $name, string $description, array $definition, \Closure $code): Command
    {
        $command = new Command($name);
        $command->setDescription($description)->setDefinition($definition);
        $command->setCode(function (InputInterface $in, OutputInterface $out) use ($code): int {
            try {
                return (int) ($code($in, $out) ?? Command::SUCCESS);
            } catch (InvalidConfigException $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::INVALID;
            } catch (InvalidPostException $e) {
                $out->writeln('<error>Refused before anything was sent:</error>');
                foreach ($e->violations as $violation) {
                    $out->writeln('  - '.$violation);
                }

                return Command::FAILURE;
            } catch (OmnipostException $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::FAILURE;
            }
        });

        return $command;
    }

    private function providers(OutputInterface $out): void
    {
        $set = static fn (string $key) => false !== getenv($key) && '' !== getenv($key);
        $able = static fn (array $alternatives) => (bool) array_filter($alternatives, static fn (array $keys) => !array_filter($keys, static fn (string $k) => !$set($k)));
        $wants = static fn (array $alternatives) => implode(' or ', array_map(static fn (array $keys) => implode(', ', $keys), $alternatives));

        $table = new Table($out);
        $table->setHeaders(['Provider', 'Factory', 'Installed', 'Configured', 'Does']);
        foreach ($this->config as $name => $p) {
            $installed = isset($this->factories[$p['factory']]);
            $read = $able($p['needs']['read']);
            $publish = $able($p['needs']['publish']);
            $configured = match (true) {
                !$installed => '',
                $read && $publish => '<info>yes</info>',
                $read => '<comment>to read; to publish, needs '.$wants($p['needs']['publish']).'</comment>',
                $publish => '<comment>to publish; to read, needs '.$wants($p['needs']['read']).'</comment>',
                default => '<comment>no: needs '.$wants($p['needs']['read']).'</comment>',
            };
            $does = '';
            if ($installed) {
                $provider = $this->registry->get($name);
                $does = implode(' ', array_keys(array_filter([
                    'publish' => $provider instanceof PublisherInterface,
                    'feed' => $provider instanceof FeedInterface,
                    'refresh' => $provider instanceof RefreshableInterface,
                    'oauth' => $provider instanceof OAuthInterface,
                ])));
            }
            $table->addRow([$name, $p['factory'], $installed ? '<info>yes</info>' : '<comment>no</comment>', $configured, $does]);
        }
        $table->render();
    }

    private function validate(InputInterface $in, OutputInterface $out): int
    {
        $publisher = $this->publisher($in);
        $post = $this->post($in, $publisher);
        $violations = method_exists($publisher, 'validate') ? $publisher->validate($post) : (new \Omnipost\Validator())->validate($post, $publisher->capabilities());
        if (!$violations) {
            $out->writeln('<info>No violation: '.$publisher->getName().' would take it.</info>');

            return Command::SUCCESS;
        }
        foreach ($violations as $violation) {
            $out->writeln('  - '.$violation);
        }

        return Command::FAILURE;
    }

    private function publish(InputInterface $in, OutputInterface $out): int
    {
        $publisher = $this->publisher($in);
        $publication = $publisher->publish($this->post($in, $publisher));
        $this->print($out, $publication);
        $until = time() + (int) $in->getOption('wait');
        while (!$publication->state->isFinal() && time() < $until) {
            sleep(5);
            $publication = $publisher->status($publication->id);
            $out->writeln(\sprintf('%s %s: %s', date('H:i:s'), $publication->id, $publication->state->value));
        }
        $this->print($out, $publication);
        if ($publication->permalink && $publication->isPublished()) {
            $out->writeln('<info>Published:</info> '.$publication->permalink);
        }

        return $publication->isFailed() ? Command::FAILURE : Command::SUCCESS;
    }

    /** The post the arguments describe, as the provider's network receives it. */
    private function post(InputInterface $in, PublisherInterface $publisher): Post
    {
        $url = (string) $in->getArgument('media');
        $file = $in->getOption('file') ?? (is_file($url) ? $url : null);
        $ext = strtolower(pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION));
        $kind = PostKind::tryFrom((string) $in->getOption('kind')) ?? (\in_array($ext, ['mp4', 'mov', 'webm', 'm4v'], true) ? PostKind::REEL : PostKind::IMAGE);
        $isVideo = \in_array($kind, [PostKind::REEL, PostKind::VIDEO], true) || (PostKind::STORY === $kind && 'jpg' !== $ext && 'jpeg' !== $ext);
        [$mime, $size] = $this->head($url, $file);
        $width = null !== $in->getOption('width') ? (int) $in->getOption('width') : null;
        $height = null !== $in->getOption('height') ? (int) $in->getOption('height') : null;
        $media = new Media($isVideo ? MediaKind::VIDEO : MediaKind::IMAGE, $url, $file, $mime, $width, $height, null !== $in->getOption('duration') ? (float) $in->getOption('duration') : null, $size, $in->getOption('cover'));

        $options = [];
        foreach ((array) $in->getOption('option') as $option) {
            [$key, $value] = array_pad(explode('=', $option, 2), 2, '');
            $options[$key] = $value;
        }
        $post = new Post($kind, [$media], (string) $in->getArgument('caption'), null, (array) $in->getOption('tag'), $media->coverUrl);

        return $post->withVariant(new Variant($publisher->getPlatform(), title: $in->getOption('title'), options: $options))->for($publisher->getPlatform());
    }

    /** @return array{?string, ?int} the media's type and size, as its server or its file says */
    private function head(string $url, ?string $file): array
    {
        if (null !== $file && is_file($file)) {
            return [mime_content_type($file) ?: null, filesize($file) ?: null];
        }
        if (!preg_match('#^https?://#', $url)) {
            return [null, null];
        }
        try {
            $headers = $this->http->request('HEAD', $url, ['timeout' => 10])->getHeaders(false);
        } catch (\Throwable) {
            return [null, null];
        }
        $type = $headers['content-type'][0] ?? null;

        return [$type ? trim(explode(';', $type)[0]) : null, isset($headers['content-length'][0]) ? (int) $headers['content-length'][0] : null];
    }

    private function provider(InputInterface $in): ProviderInterface
    {
        return $this->registry->get($in->getArgument('provider'));
    }

    private function publisher(InputInterface $in): PublisherInterface
    {
        $provider = $this->provider($in);

        return $provider instanceof PublisherInterface ? $provider : throw new InvalidConfigException(\sprintf('"%s" does not publish.', $provider->getName()));
    }

    private function feeder(InputInterface $in): FeedInterface
    {
        $provider = $this->provider($in);

        return $provider instanceof FeedInterface ? $provider : throw new InvalidConfigException(\sprintf('"%s" has no feed.', $provider->getName()));
    }

    private function refreshable(InputInterface $in): RefreshableInterface
    {
        $provider = $this->provider($in);

        return $provider instanceof RefreshableInterface ? $provider : throw new InvalidConfigException(\sprintf('"%s" has no token to refresh.', $provider->getName()));
    }

    private function oauth(InputInterface $in): OAuthInterface
    {
        $provider = $this->provider($in);

        return $provider instanceof OAuthInterface ? $provider : throw new InvalidConfigException(\sprintf('"%s" is not connected through OAuth.', $provider->getName()));
    }

    private function print(OutputInterface $out, mixed $result): void
    {
        $out->writeln((string) json_encode(self::plain($result), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /** Objects as their properties, dates as ISO 8601, enums as their values. */
    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \BackedEnum => $value->value,
            \is_object($value) => array_map(self::plain(...), get_object_vars($value)),
            \is_array($value) => array_map(self::plain(...), $value),
            default => $value,
        };
    }
}
