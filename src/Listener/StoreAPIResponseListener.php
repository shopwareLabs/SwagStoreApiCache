<?php declare(strict_types=1);

namespace SwagStoreAPICache\Listener;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Seo\SalesChannel\SeoUrlRoute;
use Shopware\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\System\Language\SalesChannel\LanguageRoute;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SwagStoreAPICache\SwagStoreAPICache;

class StoreAPIResponseListener
{
    /**
     * Returns an array of Store API routes that should be cached.
     * Override this method in child classes to add additional cacheable routes.
     *
     * @return array<string>
     */
    public function getCacheableStoreApiRoutes(): array
    {
        $routes = [
            /** @see ProductDetailRoute::load() */
            'store-api.product.detail',
            /** @see ProductListingRoute::load() */
            'store-api.product.listing',
            /** @see ProductListRoute::load() */
            'store-api.product.search',
            /** @see ProductReviewRoute::load() */
            'store-api.product-review.list',
            /** @see ProductSearchRoute::load() */
            'store-api.search',
            /** @see ProductSuggestRoute::load() */
            'store-api.search.suggest',
            /** @see CategoryRoute::load() */
            'store-api.category.detail',
            /** @see CmsRoute::load() */
            'store-api.cms.detail',
            /** @see PaymentMethodRoute::load() */
            'store-api.payment.method',
            /** @see ShippingMethodRoute::load() */
            'store-api.shipping.method',
            /** @see SitemapRoute::load() */
            'store-api.sitemap',
            /** @see SeoUrlRoute::load() */
            'store-api.seo.url',
            /** @see ProductCrossSellingRoute::load() */
            'store-api.product.cross-selling',
            /** @see LandingPageRoute::load() */
            'store-api.landing-page.detail',
            /** @see NavigationRoute::load() */
            'store-api.navigation',
            /** @see SalutationRoute::load() */
            'store-api.salutation',
            /** @see CountryRoute::load() */
            'store-api.country',
            /** @see LanguageRoute::load() */
            'store-api.language',
            /** @see CountryStateRoute::load() */
            'store-api.country.state',
            /** @see CurrencyRoute::load() */
            'store-api.currency',
            /** @see CustomerGroupRegistrationSettingsRoute::load() */
            'store-api.customer-group-registration.config',
            /** @see FindProductVariantRoute::load() */
            'store-api.product.find-variant'
        ];

        // Add additional routes from configuration
        $additionalRoutes = $this->systemConfigService->getString('SwagStoreAPICache.config.additionalCacheableRoutes');
        if (!empty($additionalRoutes)) {
            $additionalRoutes = array_filter(
                array_map('trim', explode("\n", $additionalRoutes)),
                static fn (string $route) => !empty($route)
            );
            $routes = array_merge($routes, $additionalRoutes);
        }

        return $routes;
    }

    public function __construct(
        #[Autowire(service: 'Shopware\Core\Framework\Adapter\Cache\CacheTracer')]
        private readonly AbstractCacheTracer $tracer,
        private readonly CartService $cartService,
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    #[AsEventListener(KernelEvents::RESPONSE)]
    public function __invoke(ResponseEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route', '');

        if (!\in_array($route, $this->getCacheableStoreApiRoutes(), true)) {
            return;
        }

        $event->getResponse()->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');
        $event->getResponse()->setPublic();
        $event->getResponse()->setSharedMaxAge(3600);
        $event->getResponse()->headers->removeCacheControlDirective('no-cache');
        $event->getResponse()->headers->addCacheControlDirective('stale-if-error', '3600');
        $event->getResponse()->headers->addCacheControlDirective('stale-while-revalidate', '300');

        $event->getResponse()->headers->set('surrogate-key', \implode(' ', $this->tracer->get('all')));
    }

    #[AsEventListener(KernelEvents::RESPONSE)]
    public function setCacheCookies(ResponseEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route', '');

        if (!str_starts_with($route, 'store-api')) {
            return;
        }

        /** @var SalesChannelContext|null $salesChannelContext */
        $salesChannelContext = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if ($salesChannelContext === null) {
            return;
        }

        [$defaultLanguageId, $defaultCurrencyId] = $this->connection->fetchNumeric(
            '
            SELECT LOWER(HEX(`language_id`)), LOWER(HEX(`currency_id`))
            FROM `sales_channel`
            WHERE `id` = ?',
            [Uuid::fromHexToBytes($salesChannelContext->getSalesChannel()->getId())]
        );

        $differences = [];

        if ($salesChannelContext->getCurrencyId() !== $defaultCurrencyId) {
            $differences[] = $salesChannelContext->getCurrencyId();
        }

        if ($salesChannelContext->getLanguageId() !== $defaultLanguageId) {
            $differences[] = $salesChannelContext->getLanguageId();
        }

        if ($salesChannelContext->getCustomerId() || $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext)->getLineItems()->count() > 0) {
            $differences[] = $salesChannelContext->getRuleIds();
            $differences[] = $salesChannelContext->getTaxState();
        }

        $currentHash = $event->getRequest()->cookies->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
        if (!empty($differences)) {
            $newValue = hash('sha256', json_encode($differences));

            if ($newValue !== $currentHash) {
                $cookie = Cookie::create(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $newValue);
                $cookie->setSecureDefault($event->getRequest()->isSecure());

                $event->getResponse()->headers->setCookie($cookie);
            }
        } elseif ($currentHash) {
            $event->getResponse()->headers->removeCookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
            $event->getResponse()->headers->clearCookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
        }
    }

    #[AsEventListener(KernelEvents::REQUEST, priority: 128)]
    public function onRequest(RequestEvent $event): void
    {
        $j = $event->getRequest()->query->get('j');

        if ($j) {
            try {
                $data = json_decode(rawurldecode($j), true, flags: \JSON_THROW_ON_ERROR);
                $event->getRequest()->request->replace(\is_array($data) ? $data : []);
                $event->getRequest()->setMethod('POST');
            } catch (\JsonException $e) {
                throw new BadRequestHttpException('The JSON payload is malformed.');
            }
        }
    }

    #[AsEventListener(SalesChannelContextSwitchEvent::class)]
    public function setNewLanguageToContext(SalesChannelContextSwitchEvent $event): void
    {
        $dataBag = $event->getRequestDataBag();

        if ($dataBag->has('languageId')) {
            $chain = $event->getSalesChannelContext()->getContext()->getLanguageIdChain();
            $chain[0] = $dataBag->get('languageId');

            $event->getSalesChannelContext()->getContext()->assign(['languageIdChain' => $chain]);
        }

        if ($dataBag->has('currencyId')) {
            $event->getSalesChannelContext()->getCurrency()->setId($dataBag->get('currencyId'));
        }
    }
}
