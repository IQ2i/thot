<?php

namespace App\Twig\Components\Layout\Menu;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent]
readonly class Items
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<array<string, mixed>>
     */
    #[ExposeInTemplate]
    public function getItems(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $pathInfo = $request->getPathInfo();

        return [
            ['name' => 'Projects', 'icon' => 'heroicons:folder', 'url' => $this->router->generate('app_project_index'), 'active' => str_starts_with($pathInfo, $this->router->generate('app_project_index'))],
            ['name' => 'Users', 'icon' => 'heroicons:users', 'url' => $this->router->generate('app_user_index'), 'active' => str_starts_with($pathInfo, $this->router->generate('app_user_index'))],
            ['name' => 'Logs', 'icon' => 'heroicons:circle-stack', 'url' => $this->router->generate('app_log_index'), 'active' => str_starts_with($pathInfo, $this->router->generate('app_log_index'))],
        ];
    }
}
