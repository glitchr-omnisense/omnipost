<?php

namespace Omnipost\Bridge\Symfony;

use Omnipost\FeedInterface;
use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\ProviderFactoryInterface;
use Omnipost\ProviderInterface;
use Omnipost\PublisherInterface;
use Omnipost\Registry;
use Omnipost\Validator;
use Omnipost\YouTube\YouTubeProviderFactory;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnipost in a Symfony application: the provider packages installed
 * (omnipost/instagram, omnipost/youtube) registered, the site's providers
 * built from configuration, Omnipost\Registry and Omnipost\Validator
 * autowired, and each provider injectable by its name:
 *
 *     omnipost:
 *         providers:
 *             instagram: { factory: instagram, options: { access_token: '%env(default::INSTAGRAM_ACCESS_TOKEN)%', app_id: '%env(default::INSTAGRAM_APP_ID)%', app_secret: '%env(default::INSTAGRAM_APP_SECRET)%' } }
 *             youtube:   { factory: youtube, options: { api_key: '%env(default::YOUTUBE_API_KEY)%', channel_id: '%env(default::YOUTUBE_CHANNEL_ID)%' } }
 *
 *     public function __construct(PublisherInterface $instagram, FeedInterface $youtube) {}
 *
 * Nothing is built, nor checked, when the container compiles: a provider is
 * built the first time it is asked for, and a token left empty only shows
 * when a call needs it (InvalidConfigException). So a site with no keys yet
 * still boots; Registry::has() says a provider is declared, and a call
 * inside try/catch InvalidConfigException says whether it is configured.
 *
 * An application's own factories (a ProviderFactoryInterface) are registered
 * too, autoconfigured.
 */
final class OmnipostBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnipost';

    /** The provider packages this bundle knows, registered when installed. */
    private const FACTORIES = [InstagramProviderFactory::class, YouTubeProviderFactory::class];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('providers')
                    ->info('The site\'s networks, by name: a factory (instagram, youtube...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{providers: array<string, array{factory: string, options: array<string, mixed>}>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(ProviderFactoryInterface::class)->addTag('omnipost.provider_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, ProviderFactoryInterface::class)) {
                $services->set($factory)->args([service('http_client')->nullOnInvalid()])->tag('omnipost.provider_factory');
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnipost.provider_factory'), $config['providers']])
            ->public();
        $services->set(Validator::class);

        foreach (array_keys($config['providers']) as $name) {
            $id = 'omnipost.provider.'.$name;
            $services->set($id, ProviderInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            // By what it does as well: a provider that is not a publisher (or has no feed) fails where it is injected as one.
            foreach ([ProviderInterface::class, PublisherInterface::class, FeedInterface::class] as $type) {
                $builder->registerAliasForArgument($id, $type, $name);
            }
        }
    }
}
