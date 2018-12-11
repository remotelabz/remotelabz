<?php

namespace App\Form;

use App\Entity\Device;
use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class NetworkInterfaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('type', ChoiceType::class, [
                'label' => 'Driver',
                'choices' => [
                    'Linux Bridge' => 'INTERFACE_TYPE_TAP',
                    'OpenVSwitch' => 'INTERFACE_TYPE_OVS'
                ]
            ])
            ->add('settings', EntityType::class, [
                'class' => NetworkSettings::class,
                'choice_label' => 'name',
            ])
            ->add('device', EntityType::class, [
                'class' => Device::class,
                'choice_label' => 'name',
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => NetworkInterface::class,
        ]);
    }
}
