<?php

namespace AppBundle\Form;

use AppBundle\Repository\DeviceRepository;
use AppBundle\Repository\Network_InterfaceRepository;
use AppBundle\Repository\SystemeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DeviceType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom')
            ->add('type','choice' , array(
                'choices' => array('virtuel' => 'Virtuel', 'physique' => 'Physique')))
            ->add('propriete')
            ->add('modele')
            ->add('marque')
//            ->add( 'Systeme', 'entity', array(
//                'class' => 'Appbundle\Entity\Systeme',
//                'property' => 'nom',
//                'query_builder' => function(SystemeRepository $er ){
//                    return $er->createQueryBuilder('s')
//                        ->where('s. = ?1')
//                        ->andWhere('w.visible = 1')
//                        ->andWhere('w.booked = 0')
//                        ->setParameter(1, $caravan);
//                                                 },
              ->add('systeme', 'entity', array(
                'class'    => 'AppBundle:Systeme',
                'property' => 'nom',
                'multiple' => false,
                'expanded' => false,
//                'query_builder' => function(DeviceRepository $repo) {
//                    return $repo->getNotUsedSystemQueryBuilder();
//                }
            ))
            ->add('interfaceControle', 'entity', array(
        'class'    => 'AppBundle:Network_Interface',
        'empty_value'   => 'Select',
        'property' => 'nomInterface',
        'multiple' => false,
        'required' => false,
        'query_builder' => function(Network_InterfaceRepository $repo) {
            return $repo->getNotUsedInterfaceControlQueryBuilder();
        }

    ))
           ->add('network_interfaces', 'entity', array(
        'class'    => 'AppBundle:Network_Interface',
        'property' => 'nomInterface',
        'multiple' => true,
               'required' => false,



        'query_builder' => function(Network_InterfaceRepository $repo) {
            return $repo->getNotUsedInterfacesQueryBuilder();
        }

    ))


//            ->add('interfaceControle', new Network_InterfaceType())
//            ->add('Network_Interfaces','collection',array(
//                                'type'           =>  new Network_InterfaceType(),
//                                'allow_add'      => true,
//                                'allow_delete'   => true

//                ))
            ->add('save','submit')
;
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Device'
        ));
    }
}
