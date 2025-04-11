<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Symfony\Bundle\SecurityBundle\Security;;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Repository\GroupRepository;
use App\Repository\LabRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\UserRepository;

class GroupInstanceType extends AbstractType
{
    private $security;
    private $groupRepository;
    private $labRepository;
    private $labInstanceRepository;
    private $userRepository;

    public function __construct(
        Security $security, 
        GroupRepository $groupRepository,
        LabRepository $labRepository,
        LabInstanceRepository $labInstanceRepository,
        UserRepository $userRepository)
    {
        $this->security = $security;
        $this->groupRepository = $groupRepository;
        $this->labRepository = $labRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, array($this, 'onPreSetData'));
        $builder->addEventListener(FormEvents::PRE_SUBMIT, array($this, 'onPreSubmit'));
        $builder->add('submit', SubmitType::class);
    }

    protected function addElements(FormInterface $form, string $slug) {
        
        $user = $this->security->getUser();

        $filter = [
            "All labs" => "allLabs"
        ];
        $group = $this->groupRepository->findOneBySlug($slug);
        
        $labs=[];
        $instances = $this->labInstanceRepository->findByGroup($group, $user);
        foreach ($instances as $instance) {
            $exists = false;
            foreach($labs as $lab) {
                if ($instance->getLab() == $lab) {
                    $exists = true;
                }
            }
            if ($exists == false) {
                array_push($labs, $instance->getLab());
            }
        }
        foreach ($labs as $lab) {
            $filter[$lab->getName()] = $lab->getUuid();
        }

        $form->add('filter', ChoiceType::class, [
            "expanded" => false,
            "multiple" => false,
            "label" => false,
            "attr" => [
                "class" => "groupInstancesFilter"
            ],              
            "choices" => $filter
        ]);
    
    }

    public function onPreSubmit(FormEvent $event) {
        $form = $event->getForm();
        $data = $event->getData();
        
        $this->addElements($form, $data["slug"]);
    }

    public function onPreSetData(FormEvent $event) {
        $data = $event->getData();
        $form = $event->getForm();

        $slug = "none";
        if (isset($data['slug'])) {
            $slug = $data['slug'];
        }
        
        $this->addElements($form, $slug);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}