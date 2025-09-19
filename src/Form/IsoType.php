<?php

namespace App\Form;

use App\Entity\Iso;
use App\Entity\Arch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormError;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class IsoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder         
            // Champ Name (obligatoire)
            ->add('name', TextType::class, [
                'label' => 'ISO name',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'The ISO name is required'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Type the ISO name'
                ]
            ])
            // Champ Architecture (lié à l'entité Arch)
            ->add('arch', EntityType::class, [
                'class' => Arch::class,
                'choice_label' => 'name',
                'required' => true,
                'label' => 'Architecture',
                'placeholder' => 'Select architecture...',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            
            // Sélecteur de type de fichier
            ->add('fileSourceType', ChoiceType::class, [
                'label' => 'ISO file',
                'mapped' => false, // Ce champ n'est pas lié à l'entité
                'choices' => [
                    'Upload un fichier' => 'upload',
                    'Utiliser une URL' => 'url',
                ],
                'expanded' => true, // Boutons radio
                'multiple' => false,
                'data' => 'upload', // Valeur par défaut
                'required' => true,
                'attr' => [
                    'class' => 'file-source-selector'
                ]
            ])
            
            // Champ pour l'upload de fichier ISO
            ->add('uploadedFile', FileType::class, [
                'label' => 'ISO file',
                'mapped' => false, // Géré manuellement dans le contrôleur
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5G', // 5 GB pour les fichiers ISO
                        'mimeTypes' => [
                            'application/octet-stream',
                            'application/x-iso9660-image',
                            'application/x-cd-image',
                        ],
                        'mimeTypesMessage' => 'Select a valid ISO filename',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control file-upload-input',
                    'accept' => '.iso'
                ]
            ])
            
            // Champ pour l'URL du fichier ISO
            ->add('Filename_url', UrlType::class, [
                'label' => 'URL du fichier ISO',
                'required' => false,
                'constraints' => [
                    new Url([
                        'message' => 'Select a valid URL'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control url-input',
                    'placeholder' => 'https://exemple.com/fichier.iso'
                ]
            ])

            
            ->add('description', TextareaType::class, [
                'label' => 'ISO Description (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Type a description for this ISO (optional)',
                    'maxlength' => '250'
                ]
            ])
            
            ->add('submit', SubmitType::class, [
                'label' => 'Submit',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);

        // Événement pour gérer la logique de présélection
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $iso = $event->getData();
            $form = $event->getForm();
            
            if ($iso && $iso->getFilenameUrl()) {
                // Si une URL existe déjà, présélectionner le type URL
                $form->get('fileSourceType')->setData('url');
            } elseif ($iso && $iso->getFilename()) {
                // Si un fichier existe déjà, présélectionner le type upload
                $form->get('fileSourceType')->setData('upload');
            }
        });

        // Événement pour valider qu'au moins une source est fournie
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $iso = $event->getData();
            
            $fileSourceType = $form->get('fileSourceType')->getData();
            $uploadedFile = $form->get('uploadedFile')->getData();
            $filenameUrl = $iso->getFilenameUrl();
            
            // Validation conditionnelle
            if ($fileSourceType === 'upload' && !$uploadedFile && !$iso->getFilename()) {
                $form->get('uploadedFile')->addError(new \Symfony\Component\Form\FormError('Select an ISO file'));
            }
            
            if ($fileSourceType === 'url' && !$filenameUrl) {
                $form->get('Filename_url')->addError(new \Symfony\Component\Form\FormError('Please type an URL'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Iso::class,
        ]);
    }
}