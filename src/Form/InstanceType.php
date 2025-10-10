<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InstanceType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'filter' => 'none',
            'subFilter' => 'allInstances',
        ]);
    }
}