<?php

namespace App\Form;

use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Connexion;
use App\Entity\NetworkSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Bridge\Doctrine\Form\Type\NetworkSettingsType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LabType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('shortDescription')
            ->add('description', TextareaType::class, [
                'attr' => [
                    'class' => 'mde'
                ],
                'required' => false
            ])
            ->add('devices', EntityType::class, [
                'class' => Device::class,
                'choice_label' => 'name',
                'by_reference' => false,
                'multiple' => true
            ])
            /*   ->add('connexions', EntityType::class, [
                'class' => Connexion::class,
                'choice_label' => 'name',
                'by_reference' => false,
                'multiple' => true,
                'required' => false
            ])*/
            ->add('isInternetAuthorized', CheckboxType::class, [

            ])
            ->add('hasTimer', CheckboxType::class)
            ->add('timer')
            ->add('submit', SubmitType::class)
            ->add('reset', ResetType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lab::class,
            "allow_extra_fields" => true
        ]);
    }
}
