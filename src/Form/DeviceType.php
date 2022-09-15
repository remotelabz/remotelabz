<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Flavor;
use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use App\Entity\NetworkInterface;
use App\Entity\ControlProtocol;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

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
                 'choices' => ['Virtual Machine' => 'vm', 'Container' => 'container'],
                 'help' => 'Nature of the device.',
                 'empty_data' => 'vm'
             ])
/*             ->add('hypervisor', ChoiceType::class, [
                'choices' => ['QEMU' => 'qemu', 'LXC' => 'lxc'],
                'help' => 'Nature of the device. Only Virtual Machine is supported for now.',
                'empty_data' => 'qemu'
            ])*/
            ->add('hypervisor', EntityType::class, [
                'class' => Hypervisor::class,
                'choice_label' => 'name',
                'help' => 'Type of hypervisor.',
            ])
          /*  ->add('labs', EntityType::class, [
                'class' => Lab::class,
                'choice_label' => 'name',
                'by_reference' => false,
                'multiple' => true
            ])
            */
            ->add('operatingSystem', EntityType::class, [
                'class' => OperatingSystem::class,
                'choice_label' => 'name',
                'help' => 'Image disk used for this device.'
            ])
            ->add('flavor', EntityType::class, [
                'class' => Flavor::class,
                'choice_label' => 'name'
            ])
          /*  ->add('networkInterfaces', EntityType::class, [
                'class' => NetworkInterface::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'row_attr' => ['class' => 'd-none'],
                'label_attr' => ['class' => 'd-none'],
                'attr' => ['class' => 'd-none']
            ])*/
            ->add('controlProtocols', EntityType::class, [
                'class' => ControlProtocol::class,
                'choice_label' => 'name',
                //'mapped' => true,
                'multiple' => true,
                'required' => false/*
                'row_attr' => ['class' => 'd-none'],
                'label_attr' => ['class' => 'd-none'],
                'attr' => ['class' => 'd-none']*/
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
            "allow_extra_fields" => true
        ]);
    }
}
