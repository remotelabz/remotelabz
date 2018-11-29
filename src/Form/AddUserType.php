<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Swarm;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
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
            ->add('swarms', EntityType::class, [
                'class' => Swarm::class,
                'choice_label' => "name",
                'multiple' => true,
            ])
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
