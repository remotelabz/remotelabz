<?php

namespace App\Form;

use App\Entity\Arch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


class ArchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('Name', ChoiceType::class, [
                'choices' => [
                    'x86' => 'x86',
                    'x86_64' => 'x86_64',
                    'arm' => 'arm',
                    'arm64' => 'arm64',
                ],
                'label' => 'Architecture',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Arch::class,
        ]);
    }
}