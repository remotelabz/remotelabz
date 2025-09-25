<?php

namespace App\Form;

use App\Entity\Hypervisor;
use App\Entity\OperatingSystem;
use App\Entity\Arch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;


class OperatingSystemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('imageUrl', UrlType::class, [
                'label' => 'Image URL',
                'help' => 'Provide either an image URL or upload a file, but not both.',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com/path/to/image.img'
                ]
            ])
            ->add('image_Filename', TextType::class, [
                'required' => false
            ])
            ->add('hypervisor', EntityType::class, [
                'class' => Hypervisor::class,
                'placeholder' => 'Select an hypervisor...',
                'choice_label' => 'name'
            ])
            ->add('uploaded_filename', HiddenType::class, [
                'mapped' => false, // Géré manuellement dans le contrôleur
                'required' => false
            ])
            ->add('imageFilename', FileType::class, [
                'label' => 'Upload Image File',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'accept' => '.img'
                ],
                'constraints' => [
                    new File([
                        'mimeTypes' => [
                            'application/octet-stream',
                            'application/x-qemu-disk',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid disk image file.',
                    ])
                ],
            ])
            ->add('arch', EntityType::class, [
                'class' => Arch::class,
                'choice_label' => 'name',
                'required' => true,
                'label' => 'Architecture',
                'placeholder' => 'Select architecture...',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Brief description of this operating system...',
                    'rows' => 3,
                    'maxlength' => 500
                ],
                'help' => 'Optional description (max 500 characters)'
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperatingSystem::class,
        ]);
    }
}
