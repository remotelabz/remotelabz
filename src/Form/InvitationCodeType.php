<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\CallbackTransformer;

class InvitationCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emailAdresses', TextareaType::class, [
            ])
            ->add('duration', TimeType::class, [
                'placeholder' => [
                    'hour' => 'Hour', 'minute' => 'Minute',
                ],
                'input' => 'array',
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
                    $line = preg_replace("(\n|\r|\r\n)",',',(string)$emailsAsString);
                    return array_filter(explode(',', (string)$line));
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
