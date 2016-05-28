<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigReseauType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('iP')
            ->add('iPv6')
            ->add('iPDNS')
            ->add('iPGateway')
            ->add('masque')
            ->add('protocole','choice', array(
                'choices' => array('vnc' => 'VNC', 'telnet' => 'Telnet'))
            )
        ->add('port');
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\ConfigReseau'
        ));
    }
}
