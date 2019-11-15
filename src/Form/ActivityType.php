<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Activity;
use App\Entity\ActivityAuthorization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
            ->add('internetAllowed', CheckboxType::class, [
                'help' => 'If the laboratory is allowed to be conntected to Internet.',
                'required' => false,
            ])
            ->add('interconnected', CheckboxType::class, [
                'help' => 'If all laboratory of the same activity of the same course is together interconnected.',
                'required' => false
            ])
            ->add('scope', ChoiceType::class, [
                'choices' => [
                    'Single user' => Activity::SCOPE_SINGLE_USER,
                    'Group' => Activity::SCOPE_GROUP,
                    'Course' => Activity::SCOPE_COURSE
                ],
                'expanded' => true,
                'help' => "Choose how this activity must be achieved. An activity may be done either by a single user, a group of users or the whole course."
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
