<?php

namespace AppBundle\Form;

use AppBundle\Repository\LABRepository;
use AppBundle\Repository\TPRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class TPType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            -> add('lab', 'entity', array(
                'class'    => 'AppBundle:LAB',
                'property' => 'nomlab',
                'multiple' => false,                            
            ))
            ->add('nom','text')
            ->add('file',FileType::class, array('label' => 'Sujet du TP'))
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
