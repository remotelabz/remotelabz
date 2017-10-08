<?php

namespace AppBundle\Form;

use AppBundle\Repository\LABRepository;
use AppBundle\Repository\TPRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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
            ->add('nom',TextType::class)
			->add('type', ChoiceType::class, array(
			'choices' => array (
				'individuel' => 'Individuel',
				'groupe' => 'Groupe',
			),

            'multiple' => false,                            

			))
			->add('access', ChoiceType::class, array(
			'choices' => array (
				'web' => 'Via le navigateur',
				'vpn' => 'Direct par VPN',
			),
            'multiple' => false,                            
			))

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
