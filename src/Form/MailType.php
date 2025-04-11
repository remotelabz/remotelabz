<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Form\CallbackTransformer;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;

class MailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('to', TextType::class, [
            ])
            /*->add('cc', TextType::class, [
                'required' => false
            ])
            ->add('cci', TextType::class, [
                'required' => false
            ])*/
            ->add('subject', TextType::class, [              
            ])
            ->add('content', TextareaType::class, [              
                ])
            ->add('submit', SubmitType::class)
        ;

        $builder->get('to')
            ->addModelTransformer(new CallbackTransformer(
                function ($emailsAsArray): string {
                    // transform the array to a string
                    return implode(',', (array)$emailsAsArray);
                },
                function ($emailsAsString): array {
                    // transform the string back to an array
                    return explode(',', (string)$emailsAsString);
                }
            ));

            /*$builder->get('cc')
            ->addModelTransformer(new CallbackTransformer(
                function ($emailsAsArray): string {
                    // transform the array to a string
                    return implode(', ', (array)$emailsAsArray);
                },
                function ($emailsAsString): array {
                    // transform the string back to an array
                    return explode(', ', (string)$emailsAsString);
                }
            ));

            $builder->get('cci')
            ->addModelTransformer(new CallbackTransformer(
                function ($emailsAsArray): string {
                    // transform the array to a string
                    return implode(', ', (array)$emailsAsArray);
                },
                function ($emailsAsString): array {
                    // transform the string back to an array
                    return explode(', ', (string)$emailsAsString);
                }
            ));*/
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
