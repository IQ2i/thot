<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Gitlab;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Gitlab>
 */
class GitlabType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectUrl', UrlType::class, [
                'label' => 'form.gitlab_project_url',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('accessToken', TextType::class, [
                'label' => 'form.gitlab_access_token',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Gitlab::class,
        ]);
    }
}
