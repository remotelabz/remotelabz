<?php

namespace AppBundle\Form;

use AppBundle\Repository\LABRepository;
use AppBundle\Repository\TPRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TPType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            -> add('labs', 'entity', array(
                'class'    => 'AppBundle:LAB',
                'property' => 'nomlab',
                'multiple' => true,
                'query_builder' => function(LABRepository $repo) {
                    return $repo->getLabWithoutTpQueryBuilder();
                }
                            
            ))
            ->add('nom','text')
            ->add('file','file')
            ->add('save','submit')
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\TP'
        ));
    }
}
