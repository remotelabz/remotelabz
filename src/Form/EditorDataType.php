<?php

namespace App\Form;

use App\Entity\EditorData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class EditorDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('x', IntegerType::class, [
                'empty_data' => "0"
            ])
            ->add('y', IntegerType::class, [
                'empty_data' => "0"
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditorData::class,
            "allow_extra_fields" => true
        ]);
    }
}
