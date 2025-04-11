<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class NewPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('newPassword', PasswordType::class, [
                'help' => 'Your new password',
                'mapped' => false
            ])
            ->add('confirmPassword', PasswordType::class, [
                'help' => 'This must match your new password',
                'mapped' => false
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Set new password'
            ])
        ;
    }
}