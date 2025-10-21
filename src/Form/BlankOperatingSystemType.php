<?php

namespace App\Form;

use App\Entity\OperatingSystem;
use App\Entity\FlavorDisk;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class BlankOperatingSystemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Operating System Name',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter OS name...',
                    'class' => 'form-control'
                ]
            ])
            ->add('flavorDisk', EntityType::class, [
                'class' => FlavorDisk::class,
                'choice_label' => function(FlavorDisk $flavorDisk) {
                    return $flavorDisk->getName() . ' (' . $flavorDisk->getDisk() . ' GB)';
                },
                'required' => true,
                'label' => 'Disk Flavor',
                'placeholder' => 'Select a disk flavor...',
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Optional: Select a disk size configuration'
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Brief description of this blank operating system...',
                    'rows' => 3,
                    'maxlength' => 500,
                    'class' => 'form-control'
                ],
                'help' => 'Optional description (max 500 characters)'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Create Blank OS',
                'attr' => [
                    'class' => 'btn btn-success'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperatingSystem::class,
        ]);
    }
}