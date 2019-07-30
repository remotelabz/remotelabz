<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Activity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ActivityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('description', TextareaType::class, [
                'attr' => [
                    'class' => 'mde'
                ],
                'required' => false
            ])
            ->add('lab', EntityType::class, [
                'class' => Lab::class,
                'choice_label' => 'name',
                'required' => false
            ])
            ->add('shared', HiddenType::class, [
                'data' => "false"
            ])
            ->add('supervised', HiddenType::class, [
                'data' => "false"
            ])
            ->add('accessType', HiddenType::class, [
                'data' => 'non'
            ])
            ->add('submit', SubmitType::class)
            ->add('reset', ResetType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Activity::class,
        ]);
    }
}
