<?php

namespace App\Form;

use App\Entity\Connexion;
use App\Entity\NetworkInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ConnexionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('vlan1')
            ->add('vlan2')
            ->add('networkInterface1', EntityType::class, [
                'class' => NetworkInterface::class,
                'choice_label' => 'name',
            ])
            ->add('networkInterface2', EntityType::class, [
                'class' => NetworkInterface::class,
                'choice_label' => 'name',
            ])
            ->add('submit', SubmitType::class)
            ->add('reset', ResetType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Connexion::class,
        ]);
    }
}
