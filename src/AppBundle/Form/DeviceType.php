<?php

namespace AppBundle\Form;

use AppBundle\Repository\DeviceRepository;
use AppBundle\Repository\Network_InterfaceRepository;
use AppBundle\Repository\SystemeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;


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
//
              ->add('systeme', 'entity', array(
                'class'    => 'AppBundle:Systeme',
                'property' => 'nom',
                'multiple' => false,
                'expanded' => false,

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
<<<<<<< HEAD
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
=======
		'multiple' => true,
        'required' => false,
		'query_builder' => function(Network_InterfaceRepository $repo) {
			return $repo->getNotUsedInterfaceControlQueryBuilder();
		}
		))
        ->add('save','submit')
		;
>>>>>>> e0beab26dcad40a96676c7f08edf1f255581c08a
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
