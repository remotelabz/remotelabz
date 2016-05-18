<?php

namespace AppBundle\Form;

use AppBundle\Entity\Device;
use AppBundle\Entity\POD;
use AppBundle\Repository\DeviceRepository;
use AppBundle\Repository\PODRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConnexionType extends AbstractType



{


    protected $em;

    function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nomconnexion', 'text')
            ->add('pod', 'entity', array(
                'class' => 'AppBundle:POD',
                'property' => 'nom',
                'multiple' => false,
                'required' => false,
                'empty_value' => '-- Choose a pod --',
                'query_builder' => function (PODRepository $repo) {
                    return $repo->getNotUsedPodQueryBuilder();

                }))
            ->add('Device1','choice')
            ->add('Interface1','choice')
            ->add('Device2','choice')
            ->add('Interface2','choice')
            ->add('Suivant', 'submit');
            }
//            ->add('Device1')
//            ->add('Device2')
//            ->add('Interface1')
//            ->add('Interface2')
//        ;

        // Add listeners
//        $builder->addEventListener(FormEvents::PRE_SET_DATA, array($this, 'onPreSetData'));
//        $builder->addEventListener(FormEvents::PRE_SUBMIT, array($this, 'onPreSubmit'));
//    }

//    protected function addElements(FormInterface $form, POD $pod = null) {
//        // Remove the submit button, we will place this at the end of the form later
//        $submit = $form->get('Suivant');
//        $form->remove('Suivant');
//        // Add the province element
//        $form->add('pod', 'entity', array(
//                'data' => $pod,
//                'empty_value' => '-- Choose --',
//                'class' => 'AppBundle:POD',
//                'mapped' => false)
//        );
//        // device are empty, unless we actually supplied a pod
//        $devices = array();
//
//       if ($pod) {
//            // Fetch the device from specified pod
//            $repo = $this->em->getRepository('AppBundle:Device');
//            $devices = $repo->findByPod($pod);
//
//       }
//        // Add the device element
//        $form->add('nomdevice1', 'entity', array(
//            'empty_value' => '-- Select a device first --',
//            'class' => 'AppBundle:Device',
//            'choices' => $devices,
//        ));
//        $form->add('interface1', 'entity', array(
//            'empty_value' => '-- Select interface first --',
//            'class' => 'AppBundle:Network_Interface',
////            'choices' =>$interfaces
//        ));
//
//
//
//        // Add submit button again, this time, it's back at the end of the form
//        $form->add($submit);
//    }
//    function onPreSubmit(FormEvent $event) {
//        //die('presubmit');
//        $form = $event->getForm();
//        $data = $event->getData();
//        // Note that the data is not yet hydrated into the entity.
//        $pod = $this->em->getRepository('AppBundle:POD')->find($data['pod']);
//        $this->addElements($form, $pod);
//    }
//    function onPreSetData(FormEvent $event) {
//       ;
//        $connexion = $event->getData();
//        $form = $event->getForm();
//
//        $pod = $connexion-> getNomdevice1() ? $connexion-> getNomdevice1()-> getPod() : null;
//        $this->addElements($form, $pod);
//    }



    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Connexion'
        ));
    }
    public function getName()
    {
        return "connexion_type";
    }
}
