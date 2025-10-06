<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Redmine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Redmine>
 */
class RedmineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectUrl', UrlType::class, [
                'label' => 'URL du project Redmine',
                'required' => false,
            ])
            ->add('accessToken', TextType::class, [
                'label' => 'Token d\'accès à l\'API Redmine',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Redmine::class,
        ]);
    }
}
