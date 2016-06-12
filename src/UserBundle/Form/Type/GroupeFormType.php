<?php

namespace UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\AbstractTypeExtension;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class GroupeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
       // parent::buildForm($builder, $options);

        // add your custom field
		
		$builder->add('User', EntityType::class, array(
		'class' => 'UserBundle:User',
		'choice_label' => 'label',
		'multiple' => true,
		'expanded' => true
		)		
		);
		
		$builder->add('Groupe', EntityType::class, array(
		'class' => 'UserBundle:Groupe',
		'property' => 'nom',
		'multiple' => false,
		'expanded' => false
		)		
		);
		$builder->add('submit', SubmitType::class);
    }

/*    public function getParent()
    {
        return ;
    }*/
}