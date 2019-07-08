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
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
            ->add('confirmPassword', PasswordType::class, [
                'mapped' => false
            ])
            ->add('lastName', TextType::class)
            ->add('firstName', TextType::class)
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Student' => 'ROLE_USER',
                    'Teacher' => 'ROLE_TEACHER',
                    'Administrator' => 'ROLE_ADMINISTRATOR'
                ],
                'multiple' => true,
            ])
            ->add('courses', EntityType::class, [
                'class' => Course::class,
                'choice_label' => "name",
                'multiple' => true,
            ])
            ->add('profilePictureFilename', FileType::class)
            ->add('enabled', CheckboxType::class)
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
