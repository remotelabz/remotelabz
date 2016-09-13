<?php

namespace AppBundle\Form;

use AppBundle\Entity\Device;
use AppBundle\Entity\POD;
use AppBundle\Repository\DeviceRepository;
use AppBundle\Repository\Network_InterfaceRepository;
use AppBundle\Repository\PODRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Connexion_select_podType extends AbstractType

{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('pod', 'entity', array(
                'class' => 'AppBundle:POD',
                'property' => 'nom',
                'multiple' => false,
                'required' => false,
                'empty_value' => '-- Choose a device --',
                'query_builder' => function (PODRepository $repo) {
                    return $repo->getNotUsedPodQueryBuilder();
                }))
        ->add('Suivant','submit');
    }

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
        return "connexion_select_pod";
    }
}
