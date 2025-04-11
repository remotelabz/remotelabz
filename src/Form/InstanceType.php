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
use App\Repository\UserRepository;
use App\Repository\ConfigWorkerRepository;


class InstanceType extends AbstractType
{
    private $security;
    private $groupRepository;
    private $labRepository;
    private $userRepository;
    private $configworkerRepository;

    public function __construct(
        Security $security, 
        GroupRepository $groupRepository,
        LabRepository $labRepository,
        UserRepository $userRepository,
        ConfigWorkerRepository $configworkerRepository)
    {
        $this->security = $security;
        $this->groupRepository = $groupRepository;
        $this->labRepository = $labRepository;
        $this->userRepository = $userRepository;
        $this->configworkerRepository = $configworkerRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, array($this, 'onPreSetData'));
        $builder->addEventListener(FormEvents::PRE_SUBMIT, array($this, 'onPreSubmit'));
        $builder->add('submit', SubmitType::class);
    }

    protected function addElements(FormInterface $form, string $filter = "none") {
        
        $user = $this->security->getUser();

        if ($user->isAdministrator()) {
            $form->add('filter', ChoiceType::class, [
                "expanded" => false,
                "multiple" => false,
                "label" => false,
                "attr" => [
                    "class" => "instancesFilter"
                ],              
                "choices" => [
                    "None" => "none",
                    "Group" => "group",
                    "Lab" => "lab",
                    "Student" => "student",
                    "Teacher" => "teacher",
                    "Editor" => "editor",
                    "Administrator" => "admin",
                    "Worker" => "worker"
                ]
            ]);
        }
        else {
            $form->add('filter', ChoiceType::class, [
                "expanded" => false,
                "multiple" => false,
                "label" => false,
                "attr" => [
                    "class" => "instancesFilter"
                ],  
                "choices" => [
                    "None" => "none",
                    "Group" => "group",
                    "Lab" => "lab",
                    "Student" => "student",
                    "Teacher" => "teacher",
                    "Editor" => "editor",
                ]
            ]);
        }

        $subFilter = [
            "All instances" => "allInstances"
        ];
        
        if ($filter == "group") {
            $subFilter = [
                "All groups" => "allGroups"
            ];
            if ($user->isAdministrator()) {
                $groups = $this->groupRepository->findAll();
            }
            else {
                $groups = $user->getGroupsInfo();
            }

            foreach ($groups as $group) {
                $subFilter[$group->getName()] = $group->getUuid();
            }
        }
        else if ($filter == "lab") {
            $subFilter = [
                "All labs" => "allLabs"
            ];
            if ($user->isAdministrator()) {
                $labs = $this->labRepository->findBy(["isTemplate"=>false]);
            }
            else if ($user->hasRole("ROLE_TEACHER") || $user->hasRole("ROLE_TEACHER_EDITOR")){
                $labs = $this->labRepository->findByAuthorAndGroups($user);
            }

            foreach ($labs as $lab) {
                $subFilter[$lab->getName()] = $lab->getUuid();
            }
        }
        else if ($filter == "student" || $filter == "teacher" || $filter == "editor" || $filter == "admin") {
            $subFilter = [];
            if ($user->isAdministrator()) {
                if ($filter == "admin") {
                    $role = "%ADMIN%";
                    $subFilter = [
                        "All administrators" => "allAdmins"
                    ];
                }
                else if ($filter == "teacher") {
                    $role = "%TEACHER__";
                    $subFilter = [
                        "All teachers" => "allTeachers"
                    ];
                }
                else if ($filter == "editor") {
                    $role = "%EDITOR%";
                    $subFilter = [
                        "All editors" => "allEditors"
                    ];
                }
                else {
                    $subFilter = [
                        "All students" => "allStudents"
                    ];
                    $role = "%USER%";
                }

                $users = $this->userRepository->findByRole($role);

            }
            else {
                if ($filter == "teacher") {
                    $role = "teachers";
                    $subFilter = [
                        "All teachers" => "allTeachers"
                    ];
                }
                else if ($filter == "editor") {
                    $role = "editors";
                    $subFilter = [
                        "All editors" => "allEditors"
                    ];
                }
                else {
                    $subFilter = [
                        "All students" => "allStudents"
                    ];
                    $role = "students";
                }
                $users = $this->userRepository->findUserTypesByGroups($role, $user);
            }
            usort($users, function ($a,$b) {return strcmp($a->getLastName(), $b->getLastName());});
            foreach ($users as $user) {
                $subFilter[$user->getName()] = $user->getUuid();
            }
           
        } else if ($filter == "worker"){
            $subFilter = [
                "All workers" => "allWorkers"
            ];
            $workers = $this->configworkerRepository->findAll();
            foreach($workers as $worker ){
                $subFilter[$worker->getIPv4()] = $worker->getIPv4();
            }

        }

        $form->add('subFilter', ChoiceType::class, [
            "expanded" => false,
            "multiple" => false,
            "label" => false,
            "attr" => [
                "class" => "subFilter"
            ],  
            "choices" => $subFilter
        ]);
    }

    public function onPreSubmit(FormEvent $event) {
        $form = $event->getForm();
        $data = $event->getData();
        
        $this->addElements($form, $data["filter"]);
    }

    public function onPreSetData(FormEvent $event) {
        $data = $event->getData();
        $form = $event->getForm();

        $filter = "none";
        if (isset($data['filter'])) {
            $filter = $data['filter'];
        }
        
        $this->addElements($form, $filter);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}