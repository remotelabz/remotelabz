<?php

namespace App\Form;

use App\Entity\Flavor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class FlavorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('memory', IntegerType::class, [
                'help' => 'Memory is exprimed in MB (megabytes).'
            ])
            ->add('disk', IntegerType::class, [
                'help' => 'Disk capacity is exprimed in GB (gigabytes).'
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Flavor::class,
        ]);
    }
}
