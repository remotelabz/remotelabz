<?php

namespace App\Form;

use App\Entity\Device;
use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class NetworkInterfaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('type', HiddenType::class, [
                'data' => 'tap',
                'empty_data' => 'tap',
                'required' => false
            ])
            ->add('vlan', IntegerType::class, [
                'empty_data' => "0"
            ])
            ->add('device', EntityType::class, [
                'class' => Device::class,
                'choice_label' => 'name',
                'required' => false
            ])
            ->add('isTemplate', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'Template',
                'help' => "Check this if this network interface is a template meant to be re-used in the Lab editor."
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NetworkInterface::class,
            'allow_extra_fields' => true
        ]);
    }
}
