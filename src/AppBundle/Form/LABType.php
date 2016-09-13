<?php

namespace AppBundle\Form;

use AppBundle\Entity\Connexion;
use AppBundle\Entity\POD;
use AppBundle\Repository\PODRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
class LABType extends AbstractType
{



    private $em;

    function __construct(EntityManager $em=null)
    {
        $this->em = $em;


    }
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $con = array();
        $builder
            ->add('nomlab')
            ->add('pod', 'entity', array(
                'class'    => 'AppBundle:POD',
                'property' => 'nom',
                'multiple' => false,
				'expanded' => false,
                'required' => true,
                'empty_data'   => 'toto',
				'placeholder'   => 'Choose your pod',
            ))
			
			;
		$builder->add('Add','submit');
			
		$builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
			$form = $event->getForm();
			$data = $event->getData('pod');
			
			if ($data) {
				$form->remove('connexions');
				/**
				$id_pod = $pod->getId();
				$connexion = $this->em->getRepository('AppBundle:Connexion')->getConnexionByPOD_QueryBuilder($id_pod);
				$choices=array();
				for ($i=0; count($connexion); $i++) {
				$choices[$i]=$connexion->getId()[$i]; **/
				$form->add('connexions', 'entity',array(
                'class'    => 'AppBundle:Connexion',
                'property' => 'nomconnexion',
                'multiple' => true));
				}
		});
			
	}
	
			
		

/**
       $formModifie1 = function(FormEvent $e) use ($ff){
			$data = $e->getData();
			$form=$e->getForm();
			$form->remove('template');
			$pod = isset($data['pod'])?$data['pod']:null;
            if ($pod) {
				$id_pod = $pod->getId();
				$connexion = $this->em->getRepository('AppBundle:Connexion')->getConnexionByPOD_QueryBuilder($id_pod);
				$choices = array($connexion->getId());
			

			} else
				$choices = array('1' => '1', '2' => '2');
			
			$form->add($ff->createNamed('template', 'choice', null, compact('choices')));    				
        };
		
		**/
		

   
		
    
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
