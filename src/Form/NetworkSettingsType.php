<?php

namespace App\Form;

use App\Entity\NetworkSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class NetworkSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('ip')
            ->add('ipv6')
            ->add('prefix4')
            ->add('prefix6')
            ->add('gateway')
            ->add('protocol')
            ->add('port')
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => NetworkSettings::class,
        ]);
    }
}
