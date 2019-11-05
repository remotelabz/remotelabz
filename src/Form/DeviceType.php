<?php

namespace App\Form;

use App\Entity\Device;
use App\Entity\Flavor;
use App\Form\EditorDataType;
use App\Entity\OperatingSystem;
use App\Entity\NetworkInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

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
            ->add('brand')
            ->add('model')
            // ->add('type', ChoiceType::class, [
            //     'choices' => ['Virtual Machine' => 'vm'],
            //     'help' => 'Nature of the device. Only Virtual Machine is supported for now.',
            //     'empty_data' => 'vm'
            // ])
            // ->add('hypervisor', ChoiceType::class, [
            //     'choices' => ['QEMU' => 'qemu'],
            //     'help' => 'Hypervisor used. Only QEMU is supported for now.',
            //     'empty_data' => 'qemu'
            // ])
            ->add('operatingSystem', EntityType::class, [
                'class' => OperatingSystem::class,
                'choice_label' => 'name',
                'help' => 'Image disk used for this device.'
            ])
            ->add('flavor', EntityType::class, [
                'class' => Flavor::class,
                'choice_label' => 'name'
            ])
            ->add('isTemplate', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'Template',
                'help' => "Check this if this device is a template meant to be re-used in the Lab editor."
            ])
            ->add('editorData', EditorDataType::class)
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Device::class,
            "allow_extra_fields" => true
        ]);
    }
}
