<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Group;
use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class GroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => [
                    'placeholder' => 'MathInfo 101'
                ],
                'label' => 'Group name'
            ])
            ->add('slug', TextType::class, [
                'attr' => [
                    'placeholder' => 'math-info-101',
                    'pattern' => '[a-zA-Z0-9_\.][a-zA-Z0-9_\-\.]*[a-zA-Z0-9_\-]|[a-zA-Z0-9_]',
                    'title' => 'Please choose a group URL with no special characters'
                ],
                'label' => 'Group URL'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Group description (optional)',
                'required' => false,
                'attr' => [
                    'maxlength' => '250'
                ]
            ])
            ->add('visibility', ChoiceType::class, [
                'choices' => [
                    'Private' => '0',
                    'Internal' => '1',
                    'Public' => '2',
                ],
                'expanded' => true,
                'empty_data' => '0',
                'help' => 'Who will be able to see this group?',
                'label' => 'Visibility level'
            ])
            ->add('parent', EntityType::class, [
                'class' => Group::class,
                'choice_label' => 'name',
                'required' => false,
                'help' => "Leave empty for no parent group."
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Group::class,
        ]);
    }
}
