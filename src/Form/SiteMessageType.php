<?php

namespace App\Form;

use App\Entity\Group;
use App\Entity\SiteMessage;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $options['admin'];
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => $isAdmin
                    ? SiteMessage::TYPES
                    : ['Information (connected users)' => SiteMessage::TYPE_INFORMATION],
                'label' => 'Type',
                'required' => true,
                'help' => $isAdmin
                    ? 'General: displayed on the login page. Information: persistent banner on the pages of the targeted users.'
                    : 'Information: persistent banner on the pages of the targeted users.',
            ])
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'required' => true,
                'attr' => [
                    'rows' => 4,
                ],
            ])
            ->add('targetUsers', EntityType::class, [
                'class' => User::class,
                'label' => 'Target users',
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choice_label' => function (User $user): string {
                    return $user->getName().' ('.$user->getEmail().')';
                },
                'help' => 'Select one or more users, or leave empty to target everyone.',
                'attr' => [
                    'size' => 10,
                ],
            ])
            ->add('targetGroups', EntityType::class, [
                'class' => Group::class,
                'label' => 'Target groups',
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choice_label' => 'name',
                'help' => 'Users belonging to a selected group (or one of its subgroups) will see the message.',
                'attr' => [
                    'size' => 10,
                ],
            ])
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'Expiry date',
                'required' => false,
                'input' => 'datetime',
                'widget' => 'single_text',
                'help' => 'Optional. The message will automatically no longer be displayed from this date and time.',
            ])
            ->add('expiresAtOffset', HiddenType::class, [
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SiteMessage::class,
            'admin' => false,
        ]);
        $resolver->setAllowedTypes('admin', 'bool');
    }
}
