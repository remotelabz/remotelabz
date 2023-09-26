<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\Email;

class MailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('to', EmailType::class, [
                //'attr' => ['multiple' => 'multiple'],
                //'constraints' => [new Email(['mode'=>'strict']) ]
            ])
            ->add('cc', EmailType::class, [
                //'attr' => ['multiple' => 'multiple']
            ])
            ->add('cci', EmailType::class, [
                //'attr' => ['multiple' => 'multiple']
            ])
            ->add('subject', TextType::class, [              
            ])
            ->add('content', TextareaType::class, [              
                ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
