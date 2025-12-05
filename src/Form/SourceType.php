<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Gitlab;
use App\Entity\GoogleDoc;
use App\Entity\Local;
use App\Entity\Redmine;
use App\Entity\Source;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * @extends AbstractType<Source>
 */
class SourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'form.source_type',
                'required' => false,
                'choices' => [
                    'GitLab' => Gitlab::class,
                    'Google Doc' => GoogleDoc::class,
                    'Redmine' => Redmine::class,
                    'Local' => Local::class,
                ],
            ])
            ->addDependent('content', 'type', function (DependentField $field, ?string $type): void {
                $params = [
                    'label' => false,
                ];

                match ($type) {
                    Gitlab::class => $field->add(GitlabType::class, $params),
                    GoogleDoc::class => $field->add(GoogleDocType::class, $params),
                    Redmine::class => $field->add(RedmineType::class, $params),
                    Local::class => $field->add(LocalType::class, $params),
                    default => null,
                };
            });

        $builder->addModelTransformer(new CallbackTransformer(
            function (?Source $entity): array {
                if (null === $entity) {
                    return [];
                }

                return [
                    'type' => $entity::class,
                    'content' => $entity,
                ];
            },
            fn ($data): ?Source => $data['content'] ?? null
        ));
    }
}
