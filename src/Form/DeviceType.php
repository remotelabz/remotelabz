<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Flavor;
use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use App\Entity\NetworkInterface;
use App\Entity\ControlProtocolType;
use App\Repository\OperatingSystemRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Doctrine\ORM\QueryBuilder;

class DeviceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $virtuality = $options['virtuality'];
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
            
            ->add('operatingSystem', EntityType::class, [
                'class' => OperatingSystem::class,
                'query_builder' => function(OperatingSystemRepository $operaringSystemRepository) use ($virtuality): QueryBuilder {
                    if ($virtuality == 0) {
                        return $operaringSystemRepository->createQueryBuilder('o')
                        ->join('o.hypervisor', 'h')
                        ->where('h.name = :name')
                        ->setParameter('name', 'physical');
                    }
                    else {
                        return $operaringSystemRepository->createQueryBuilder('o')
                        ->join('o.hypervisor', 'h')
                        ->where('h.name != :name')
                        ->setParameter('name', 'physical');
                    }
                    
                },
                'choice_label' => 'name',
                'help' => 'Image disk used for this device.'
            ])
            ->add('flavor', EntityType::class, [
                'class' => Flavor::class,
                'choice_label' => 'name'
            ])
            ->add('nbCpu', NumberType::class, [
                'empty_data' => '1',
                'required' => false,
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
            ->add('networkInterfaceTemplate', TextType::class, [
                'help' => 'Scheme of network interfaces name. exemple: eth',
                'empty_data' => 'eth'
            ])
           ->add('controlProtocolTypes', EntityType::class, [
                'class' => ControlProtocolType::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'help' => 'Press on CTRL when you click to delete a type',
           ]);
           if ($virtuality == 0) {
            $builder
                ->add('ip', TextType::class, [
                    'required' => true
                ])
                ->add('port', NumberType::class, [
                    'required' => true
                ]);

           }
            $builder->add('isTemplate', CheckboxType::class, [
                'required' => false,
                'data' => true,
                'label' => 'Template',
                'help' => "Check this if this device is a template meant to be re-used in the Lab editor."
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Device::class,
            "allow_extra_fields" => true,
            'nb_network_interface' => null,
            "virtuality" => 1
        ]);
    }
}
