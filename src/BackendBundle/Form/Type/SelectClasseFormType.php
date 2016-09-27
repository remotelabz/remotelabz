<?php

namespace BackendBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use UserBundle\Entity\Classe;

class SelectClasseFormType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
           ->add('nom', 'entity', array(
                'class' => 'UserBundle:Classe',
                'property' => 'nom',
                'multiple' => false,
                'required' => true,
                'empty_value' => '-- Choose a class --'
                ))
				
        ->add('Suivant','submit');
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'UserBundle\Entity\Classe'
        ));
    }
    
}
