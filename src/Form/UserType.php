<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class, [
                'mapped' => false,
                'required' => false
            ])
            ->add('confirmPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false
            ])
            ->add('lastName', TextType::class)
            ->add('firstName', TextType::class)
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Student' => 'ROLE_USER',
                    'Teacher' => 'ROLE_TEACHER',
                    'Editor teacher' => 'ROLE_TEACHER_EDITOR',
                    'Administrator' => 'ROLE_ADMINISTRATOR',
                    'Super Administrator' => 'ROLE_SUPER_ADMINISTRATOR',
                ],
                'empty_data' => 'ROLE_USER',
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'label' => 'Access level'
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'help' => 'If the user is disabled, he can\'t log in.'
            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
