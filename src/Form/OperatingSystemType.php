<?php

namespace App\Form;

use App\Entity\Flavor;
use App\Entity\Hypervisor;
use App\Entity\OperatingSystem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class OperatingSystemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('imageUrl', UrlType::class, [
                'label' => 'Image URL',
                'help' => 'You can provide either an image URL or a file, but not both.',
                'required' => false
            ])
            ->add('imageFilename', FileType::class, [
                'label' => 'Upload an image file',
                'help' => 'The maximum size allowed is 3GB. Accepted files : .img',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '3000M',
                        'mimeTypes' => [
                            "application/octet-stream"
                        ],
                        'mimeTypesMessage' => "Please upload a valid image file",
                    ])
                ],
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => OperatingSystem::class,
        ]);
    }
}
