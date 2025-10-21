<?php

namespace App\Form;

use App\Entity\FlavorDisk;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class FlavorDiskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Flavor Name',
                'required' => true,
                'attr' => [
                    'placeholder' => 'e.g., Small, Medium, Large...',
                ],
                'help' => 'Enter a descriptive name for this disk flavor'
            ])
            ->add('disk', IntegerType::class, [
                'label' => 'Disk Size (GB)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter size in GB',
                    'min' => 1
                ],
                'help' => 'Disk size in gigabytes (must be greater than 0)'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Save Flavor',
                'attr' => [
                    'class' => 'btn btn-success'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FlavorDisk::class,
        ]);
    }
}