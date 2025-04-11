<?php

namespace App\Form;

use App\Entity\PduOutletDevice;
use App\Entity\Device;
use App\Repository\DeviceRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\QueryBuilder;

class PduOutletDeviceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $device = $options['device'];
        $builder
            ->add('device', EntityType::class, [
                'class' => Device::class,
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'query_builder' => function(DeviceRepository $deviceRepository) use ($device): QueryBuilder {
                    if ($device == null) {
                        $devices = $deviceRepository->createQueryBuilder('d')
                            ->leftJoin('d.outlet', 'o')
                            ->where('d.virtuality = 0')
                            ->andWhere('d.isTemplate = 1')
                            ->andWhere('o IS NULL');
                    }
                    else {
                        $devices = $deviceRepository->createQueryBuilder('d')
                            ->leftJoin('d.outlet', 'o')
                            ->where('(d = :device) OR (d.virtuality = 0 AND d.isTemplate = 1 AND o IS NULL)')
                            ->setParameter('device', $device);
                    }
                    return $devices;
                }
                
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PduOutletDevice::class,
            'device' => null,
            "allow_extra_fields" => true
        ]);
    }
}
