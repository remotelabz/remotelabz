<?php

namespace App\Form;

use App\Entity\Device;
use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class NetworkInterfaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            // ->add('type', ChoiceType::class, [
            //     'label' => 'Driver',
            //     'choices' => [
            //         'Linux Bridge' => 'tap',
            //         'OpenVSwitch' => 'ovs'
            //     ]
            // ])
            ->add('type', HiddenType::class, [
                'data' => 'tap'
            ])
            // ->add('settings', EntityType::class, [
            //     'class' => NetworkSettings::class,
            //     'choice_label' => 'name',
            // ])
            ->add('accessType', ChoiceType::class, [
                'choices' => [
                    'VNC' => 'VNC'
                ],
                'mapped' => false,
                'required' => false
            ])
            ->add('device', EntityType::class, [
                'class' => Device::class,
                'choice_label' => 'name',
                'required' => false
            ])
            ->add('macAddress', TextType::class, [
                'label' => 'MAC address',
                'help' => 'MAC address must be in shape like 52:54:00:XX:XX:XX where X is a digit in hexa',
                //'data' => '52:54:00:',
                'required' => true
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
