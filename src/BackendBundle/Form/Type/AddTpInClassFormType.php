<?php

namespace BackendBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use UserBundle\Repository\UserRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;


use AppBundle\Entity\TP;

class AddTpInClassFormType extends AbstractType
{

	
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$classe_selected = $options['id_classe'];
        $builder
           /**
		   ->add('firstname', ChoiceType::class, array(
                'choices' => $this->list_student,
				'multiple' => 'yes',
                ))
				*/
			->add('nom', EntityType::class, array(
                'class' => 'AppBundle:TP',
                'choice_label' => 'nom',
                'multiple' => true,
                'required' => true
                ))
				
        ->add('Envoyer',SubmitType::class);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => null,
			'id_classe' => null
        ));
    }
    
}
