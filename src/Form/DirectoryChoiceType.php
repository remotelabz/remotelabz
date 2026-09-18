<?php

namespace App\Form;

use App\Entity\Directory;
use App\Service\DirectoryService;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DirectoryChoiceType extends EntityType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'class' => Directory::class,
            'required' => false,
            'label' => 'Directory',
            'placeholder' => '(Root - no directory)',
            'help' => 'Optional: organize this entry inside a directory (sub-directories are supported).',
            'attr' => [
                'class' => 'form-select'
            ],
            'choice_label' => function (?Directory $directory): string {
                if ($directory === null) {
                    return '';
                }

                return \App\Service\DirectoryService::indent($directory);
            },
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('d')
                    ->where('d.deletedAt IS NULL')
                    ->orderBy('d.path', 'ASC');
            },
        ]);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'directory_choice';
    }
}
