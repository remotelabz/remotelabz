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
                'multiple' => true,
                'required' => false,
                'empty_value'   => 'Select a pod ',
                'query_builder' => function(PODRepository $repo) {
                    return $repo->getNotUsedPodQueryBuilder();
                }
            ))
            ->add('connexions','choice', [
        'choices' => $con,
        'multiple' => true,
        'expanded' => false,
    ])
            ->add('Suivant','submit')
        ;


        $formModifie1 = function(FormInterface $form,POD $pod) {

            $id_pod = $pod->getId();
            $connexion = $this->em->getRepository('AppBundle:Connexion')->getConnexionByPOD_QueryBuilder($id_pod);

            $form->add('connexions', 'entity', array(
                'class' => 'AppBundle:Connexion',
                'property' => 'nomconnexion',
                'multiple' => true ,
                'required' => false,
                'query_builder' => $connexion
            ));
        };


        $builder->get('pod')->addEventListener(FormEvents::POST_SUBMIT,function(FormEvent $event)use ($formModifie1){
            $pod_array = $event->getForm()->getData();
            $pod= $pod_array->first();

            $formModifie1($event->getForm()->getParent(),$pod);

        });
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
