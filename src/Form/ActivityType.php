<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Activity;
use App\Entity\ActivityAuthorization;
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
            ->add('InternetAllowed', CheckboxType::class, [
                'help' => 'If the laboratory is allowed to be conntected to Internet.',
                'required' => false,
//                'block_prefix' => 'checkbox_test'
            ])
            ->add('Interconnected', CheckboxType::class, [
                'help' => 'If all laboratory of the same activity of the same course is together interconnected.',
                'required' => false
            ])
            ->add('UsedAlone', CheckboxType::class, [
                'help' => 'If this activity must be alone.',
                'required' => false
            ])
            ->add('UsedInGroup', CheckboxType::class, [
                'help' => 'If this activity must be done in group.',
                'required' => false
            ])
            ->add('UsedTogetherInCourse', CheckboxType::class, [
                'help' => 'If all users of this course do this activity together.',
                'required' => false
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
