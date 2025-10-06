<?php

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * @extends AbstractType<Project>
 */
class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('startedAt', DateType::class, [
                'label' => 'Started at',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('endedAt', DateType::class, [
                'label' => 'Ended at',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description, additional information',
                'required' => false,
            ])
            ->add('sources', LiveCollectionType::class, [
                'label' => false,
                'entry_type' => SourceType::class,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
