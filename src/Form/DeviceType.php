<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Flavor;
use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use App\Entity\NetworkInterface;
use App\Entity\ControlProtocolType;
use App\Entity\Iso;
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
use Symfony\Component\HttpKernel\KernelInterface;


class DeviceType extends AbstractType
{

    private string $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
    }

    private function getAvailableIcons(): array
    {

        $iconDir = $this->projectDir . '/public/build/images/icons/';
        $icons = [];

        foreach (new \FilesystemIterator($iconDir) as $fileInfo) {
            if ($fileInfo->isFile()) {
                $filename = $fileInfo->getFilename();
                $label = ucwords(str_replace('_', ' ', pathinfo($filename, PATHINFO_FILENAME)));
                $icons[$label] = $filename;
            }
        }
        ksort($icons);
        return $icons;
    }

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
            ->add('icon', ChoiceType::class, [
                'choices' => $this->getAvailableIcons(),
                'attr' => [
                    'class' => 'icon-selector'                
                ],
                'help' => 'Select an icon to represent this device',
                'required' => false,
                'placeholder' => 'Choose an icon...'
            ])
            ->add('operatingSystem', EntityType::class, [
                'class' => OperatingSystem::class,
                'query_builder' => function(OperatingSystemRepository $operaringSystemRepository) use ($virtuality): QueryBuilder {
                    if ($virtuality == 0) {
                        return $operaringSystemRepository->createQueryBuilder('o')
                        ->join('o.hypervisor', 'h')
                        ->where('h.name = :name')
                        ->setParameter('name', 'physical')
                        ->orderBy('o.name', 'ASC');
                    }
                    else {
                        return $operaringSystemRepository->createQueryBuilder('o')
                        ->join('o.hypervisor', 'h')
                        ->where('h.name != :name')
                        ->setParameter('name', 'physical')
                        ->orderBy('o.name', 'ASC');
                    }
                },
                'choice_label' => 'name',
                'help' => 'Image disk used for this device.'
            ])
            
            ->add('bios_type', ChoiceType::class, [
                    'choices' => [
                        'BIOS' => 'BIOS',
                        'UEFI' => 'UEFI',
                    ],
                    'required' => false,
                    'placeholder' => 'Select a BIOS type',
                    'help' => 'Firmaware type (BIOS or UEFI)',
                ])
                
                ->add('other_options', TextType::class, [
                    'required' => false,
                    'label' => 'Advanced options',
                    'help' => 'Advanced QEMU options',
                ])
                ->add('bios_filename', EntityType::class, [
                    'class' => Iso::class,
                    'choice_label' => 'filename',
                    'required' => false,
                    'placeholder' => 'Select a BIOS image',
                    'help' => 'BIOS file to use',
                ])
                ->add('cdrom_iso_filename', EntityType::class, [
                    'class' => Iso::class,
                    'choice_label' => 'filename',
                    'required' => false,
                    'placeholder' => 'Select an ISO image',
                    'help' => 'ISO file to mount as CD-ROM',
                ])
                ->add('cdrom_bus_type', ChoiceType::class, [
                    'choices' => [
                        'IDE' => 'IDE',
                        'SATA' => 'SATA',
                        'SCSI' => 'SCSI',
                        'VirtIO' => 'VirtIO',
                    ],
                    'required' => false,
                    'placeholder' => 'Sélectionner le bus du CD-ROM',
                    'help' => 'Type de bus pour le CD-ROM',
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