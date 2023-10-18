<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\CallbackTransformer;

class InvitationCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('emailAdresses', TextType::class, [
            ])
            ->add('submit', SubmitType::class)
        ;

        $builder->get('emailAdresses')
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
    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
