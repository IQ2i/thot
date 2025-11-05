<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Redmine;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Redmine>
 */
class RedmineType extends AbstractType
{
    public function __construct(
        #[Autowire(env: 'DEFAULT_REDMINE_ACCESS_TOKEN')]
        private readonly ?string $defaultAccessToken = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectUrl', UrlType::class, [
                'label' => 'form.redmine_project_url',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('accessToken', TextType::class, [
                'label' => 'form.redmine_access_token',
                'disabled' => null !== $this->defaultAccessToken,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
        ;

        $builder->get('accessToken')->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            if (null !== $this->defaultAccessToken) {
                $event->setData($this->defaultAccessToken);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Redmine::class,
        ]);
    }
}
