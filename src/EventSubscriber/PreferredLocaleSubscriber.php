<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

readonly class PreferredLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        /** @var string[] */
        #[Autowire('%kernel.enabled_locales%')]
        private array $enabledLocales,
        #[Autowire('%kernel.default_locale%')]
        private ?string $defaultLocale,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $preferredLanguage = $request->getPreferredLanguage($this->enabledLocales);

        if ($preferredLanguage !== $this->defaultLocale) {
            $this->localeSwitcher->setLocale($preferredLanguage);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
