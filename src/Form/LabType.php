<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\POD;
use App\Entity\Connexion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class LabType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('pod', EntityType::class, [
                'class' => \App\Entity\POD::class,
                'choice_label' => 'name'
            ])
            ->add('connexions', EntityType::class, [
                'class' => Connexion::class,
                'choice_label' => 'name',
                'by_reference' => false,
                'multiple' => true
            ])
            ->add('submit', SubmitType::class)
            ->add('reset', ResetType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Lab::class,
        ]);
    }
}
