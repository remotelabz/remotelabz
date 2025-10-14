<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
    private $configWorkerRepository;

    public function __construct(
        Security $security,
        GroupRepository $groupRepository,
        LabRepository $labRepository,
        UserRepository $userRepository,
        ConfigWorkerRepository $configWorkerRepository
    ) {
        $this->security = $security;
        $this->groupRepository = $groupRepository;
        $this->labRepository = $labRepository;
        $this->userRepository = $userRepository;
        $this->configWorkerRepository = $configWorkerRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $this->security->getUser();

        // Définir les choix de filtre selon le rôle
        if ($user->isAdministrator()) {
            $filterChoices = [
                "None" => "none",
                "Group" => "group",
                "Lab" => "lab",
                "Student" => "student",
                "Teacher" => "teacher",
                "Editor" => "editor",
                "Administrator" => "admin",
                "Worker" => "worker"
            ];
        } else {
            $filterChoices = [
                "None" => "none",
                "Group" => "group",
                "Lab" => "lab",
                "Student" => "student",
                "Teacher" => "teacher",
                "Editor" => "editor",
            ];
        }

        $builder
            ->add('filter', ChoiceType::class, [
                "expanded" => false,
                "multiple" => false,
                "label" => false,
                "data" => $options['filter'] ?? 'none',
                "attr" => [
                    "class" => "instancesFilter form-control"
                ],
                "choices" => $filterChoices
            ])
            ->add('subFilter', ChoiceType::class, [
                "expanded" => false,
                "multiple" => false,
                "label" => false,
                "data" => $options['subFilter'] ?? 'allInstances',
                "attr" => [
                    "class" => "subFilter form-control"
                ],
                "choices" => [
                    "All instances" => "allInstances"
                ]
            ])
            ->add('submit', SubmitType::class, [
                "label" => "Filter"
            ]);

        // Utiliser un FormEvent pour remplir les choix du subFilter en fonction du filter
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            $data = $event->getData();
            
            $filter = $data['filter'] ?? 'none';
            $subFilterChoices = $this->getSubFilterChoices($filter, $user);
            
            $form->add('subFilter', ChoiceType::class, [
                "expanded" => false,
                "multiple" => false,
                "label" => false,
                "data" => $data['subFilter'] ?? 'allInstances',
                "attr" => [
                    "class" => "subFilter form-control"
                ],
                "choices" => $subFilterChoices
            ]);
        });
    }

    private function getSubFilterChoices(string $filter, $user): array
    {
        $choices = [];

        switch ($filter) {
            case 'none':
                $choices = ["All instances" => "allInstances"];
                break;

            case 'group':
                $choices["All groups"] = "allGroups";
                if ($user->isAdministrator()) {
                    $groups = $this->groupRepository->findAll();
                } else {
                    $groups = $user->getGroupsInfo();
                }
                foreach ($groups as $group) {
                    $choices[$group->getName()] = $group->getUuid();
                }
                break;

            case 'lab':
                $choices["All labs"] = "allLabs";
                if ($user->isAdministrator()) {
                    $labs = $this->labRepository->findBy(["isTemplate" => false]);
                } else if ($user->hasRole("ROLE_TEACHER") || $user->hasRole("ROLE_TEACHER_EDITOR")) {
                    $labs = $this->labRepository->findByAuthorAndGroups($user);
                } else {
                    $labs = [];
                }
                foreach ($labs as $lab) {
                    $choices[$lab->getName()] = $lab->getUuid();
                }
                break;

            case 'student':
                $choices["All students"] = "allStudents";
                if ($user->isAdministrator()) {
                    $users = $this->userRepository->findByRole("%USER%");
                } else {
                    $users = $this->userRepository->findUserTypesByGroups("students", $user);
                }
                usort($users, function ($a, $b) {
                    return strcmp($a->getLastName(), $b->getLastName());
                });
                foreach ($users as $u) {
                    $choices[$u->getName()] = $u->getUuid();
                }
                break;

            case 'teacher':
                $choices["All teachers"] = "allTeachers";
                if ($user->isAdministrator()) {
                    $users = $this->userRepository->findByRole("%TEACHER__");
                } else {
                    $users = $this->userRepository->findUserTypesByGroups("teachers", $user);
                }
                usort($users, function ($a, $b) {
                    return strcmp($a->getLastName(), $b->getLastName());
                });
                foreach ($users as $u) {
                    $choices[$u->getName()] = $u->getUuid();
                }
                break;

            case 'editor':
                $choices["All editors"] = "allEditors";
                if ($user->isAdministrator()) {
                    $users = $this->userRepository->findByRole("%EDITOR%");
                } else {
                    $users = $this->userRepository->findUserTypesByGroups("editors", $user);
                }
                usort($users, function ($a, $b) {
                    return strcmp($a->getLastName(), $b->getLastName());
                });
                foreach ($users as $u) {
                    $choices[$u->getName()] = $u->getUuid();
                }
                break;

            case 'admin':
                $choices["All administrators"] = "allAdmins";
                if ($user->isAdministrator()) {
                    $users = $this->userRepository->findByRole("%ADMIN%");
                    usort($users, function ($a, $b) {
                        return strcmp($a->getLastName(), $b->getLastName());
                    });
                    foreach ($users as $u) {
                        $choices[$u->getName()] = $u->getUuid();
                    }
                }
                break;

            case 'worker':
                $choices["All workers"] = "allworkers";
                $workers = $this->configWorkerRepository->findAll();
                foreach ($workers as $worker) {
                    $choices[$worker->getIPv4()] = $worker->getIPv4();
                }
                break;

            default:
                $choices = ["All instances" => "allInstances"];
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'filter' => 'none',
            'subFilter' => 'allInstances',
        ]);
    }
}