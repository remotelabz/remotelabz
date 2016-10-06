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
                'choices' => array('virtuel' => 'Virtuel', 'physique' => 'Physique', 'comp_physique' => 'Comportement équivalent physique (OVS)')))
            ->add('propriete','choice' , array(
                'choices' => array('Ordi' => 'Ordinateur', 'Switch' => 'Switch', 'Routeur' => 'Router',
				'Firewall' => 'Firewall')))
            ->add('modele')
            ->add('marque')
			->add('script')
			->add('ordre')
//
             ->add('systeme', 'entity', array(
                'class'    => 'AppBundle:Systeme',
                'property' => 'nom',
                'multiple' => false,
                'expanded' => false,
				'query_builder' => function(SystemeRepository $repo) {
									return $repo->getNotUsedSystemeQueryBuilder();
								}

            ))
            ->add('interfaceControle', 'entity', array(
        'class'    => 'AppBundle:Network_Interface',
        'empty_value'   => 'Select ou Aucun',
        'property' => 'label',
        'multiple' => false,
        'required' => false,
        'query_builder' => function(Network_InterfaceRepository $repo) {
            return $repo->getNotUsedInterfaceControlQueryBuilder();
		}
		))
           ->add('network_interfaces', 'entity', array(
        'class'    => 'AppBundle:Network_Interface',
        'property' => 'nom_virtuel_physique',
        'multiple' => true,
        'required' => false,
        'query_builder' => function(Network_InterfaceRepository $repo) {
            return $repo->getNotUsedInterfacesQueryBuilder();
        }

    ))
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
