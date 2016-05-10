<?php

namespace AppBundle\Form;

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
            ->add('type')
            ->add('propriete')
            ->add('modele')
            ->add('marque')
            ->add('systeme' ,new SystemeType())
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
