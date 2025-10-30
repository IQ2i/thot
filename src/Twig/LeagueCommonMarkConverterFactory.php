<?php

namespace App\Twig;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\ExtensionInterface;

final readonly class LeagueCommonMarkConverterFactory
{
    /**
     * @param ExtensionInterface[] $extensions
     */
    public function __construct(
        private iterable $extensions,
    ) {
    }

    public function __invoke(): CommonMarkConverter
    {
        $config = [
            'external_link' => [
                'internal_hosts' => '',
                'open_in_new_window' => true,
                'html_class' => '',
                'nofollow' => '',
                'noopener' => '',
                'noreferrer' => '',
            ],
        ];

        $converter = new CommonMarkConverter($config);

        foreach ($this->extensions as $extension) {
            $converter->getEnvironment()->addExtension($extension);
        }

        return $converter;
    }
}
