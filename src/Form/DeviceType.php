<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Flavor;
use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use App\Entity\NetworkInterface;
use App\Entity\ControlProtocolType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class DeviceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => [
                    'placeholder' => 'Identifies this device'
                ]
            ])
            ->add('brand', TextType::class, [
                'required' => false,
                'empty_data' => ''
            ])
            ->add('model', TextType::class, [
                'required' => false,
                'empty_data' => ''
            ])
            ->add('type', ChoiceType::class, [
                 'choices' => ['Virtual Machine' => 'vm', 'Container' => 'container', 'Switch' => 'switch'],
                 'help' => 'Nature of the device.',
                 'empty_data' => 'vm'
             ])
            ->add('hypervisor', EntityType::class, [
                'class' => Hypervisor::class,
                'choice_label' => 'name',
                'help' => 'Type of hypervisor.',
            ])
            
            ->add('operatingSystem', EntityType::class, [
                'class' => OperatingSystem::class,
                'choice_label' => 'name',
                'help' => 'Image disk used for this device.'
            ])
            ->add('flavor', EntityType::class, [
                'class' => Flavor::class,
                'choice_label' => 'name'
            ])
            ->add('nbCpu', NumberType::class, [
                'empty_data' => 1,
                'required' => true,
            ])
            ->add('nbCore', NumberType::class, [
                'empty_data' => null,
                'required' => false
            ])
            ->add('nbSocket', NumberType::class, [
                'empty_data' => null,
                'required' => false
            ])
            ->add('nbThread', NumberType::class, [
                'empty_data' => null,
                'required' => false
            ])
            ->add('networkInterfaces', NumberType::class, [
                'data' => $options["nb_network_interface"],
                'help' => "Limit to 19 interfaces",
                'required' => true,
                'empty_data' => 1,
                'mapped' => false
            ])
            ->add('networkInterfaceTemplate', ChoiceType::class, [
                'choices' => ['eth' => 'eth', 'ens' => 'ens', 'enp0s' => 'enp0s'],
                'help' => 'Scheme of network interfaces name.',
                'empty_data' => 'eth'
            ])
           ->add('controlProtocolTypes', EntityType::class, [
                'class' => ControlProtocolType::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ])
            ->add('isTemplate', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'Template',
                'help' => "Check this if this device is a template meant to be re-used in the Lab editor."
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Device::class,
            "allow_extra_fields" => true,
            'nb_network_interface' => null
        ]);
    }
}
