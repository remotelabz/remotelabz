<?php

namespace AppBundle\Form;

use AppBundle\Repository\DeviceRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PODType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('devices', 'entity', array(
                'class'    => 'AppBundle:Device',
                'property' => 'nom',
                'multiple' => true,
                'required' => false,



                'query_builder' => function(DeviceRepository $repo) {
                    return $repo->getNotUsedDeviceQueryBuilder();
                }
            ))
            ->add('nompod')
            ->add('save','submit')
        ;
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\POD'
        ));
    }
}
