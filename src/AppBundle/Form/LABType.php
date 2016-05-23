<?php

namespace AppBundle\Form;

use AppBundle\Repository\PODRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LABType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nomlab')
            ->add('pod', 'entity', array(
                'class'    => 'AppBundle:POD',
                'property' => 'nom',
                'multiple' => false,
                'required' => false,



                'query_builder' => function(PODRepository $repo) {
                    return $repo->getNotUsedPodQueryBuilder();
                }
            ))
            ->add('connexions','choice')
            ->add('Suivant','submit')
        ;
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\LAB'
        ));
    }
}
