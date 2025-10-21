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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

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
                'mapped' => false,
                'choices' => [
                    'Upload un fichier' => 'upload',
                    'Utiliser une URL' => 'url',
                    'Nom de fichier uniquement' => 'filename',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'upload',
                'required' => true,
                'attr' => [
                    'class' => 'file-source-selector'
                ]
            ])
            ->add('uploaded_filename', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'id' => 'uploaded_filename'
                ]
            ])
            
            // Champ pour l'upload de fichier ISO
            ->add('uploadedFile', FileType::class, [
                'label' => 'ISO file',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
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

            // Champ pour le nom de fichier uniquement (fichier déjà sur le serveur)
            ->add('Filename', TextType::class, [
                'label' => 'Filename',
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'file.iso'
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
            
            if ($iso && $iso->getId()) {
                // En mode édition
                if ($iso->getFilenameUrl()) {
                    // Si une URL existe, présélectionner le type URL
                    $form->get('fileSourceType')->setData('url');
                } elseif ($iso->getFilename()) {
                    // Si un filename existe, déterminer si c'est un upload ou un filename only
                    // On suppose que si le fichier existe physiquement, c'était un upload
                    // Sinon, c'est un filename only
                    
                    // CORRECTION: On ne peut pas vraiment distinguer les deux cas ici
                    // La meilleure approche est de présélectionner 'filename' par défaut
                    // si aucun uploaded_filename n'est présent
                    $form->get('fileSourceType')->setData('filename');
                }
            }
        });

        // Événement pour valider qu'au moins une source est fournie
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $iso = $event->getData();
            
            $fileSourceType = $form->get('fileSourceType')->getData();
            $uploadedFilename = $form->get('uploaded_filename')->getData();
            $filenameUrl = $iso->getFilenameUrl();
            $filename = $iso->getFilename();
            
            // Validation conditionnelle
            if ($fileSourceType === 'upload') {
                if (!$uploadedFilename && !$filename) {
                    $form->get('uploaded_filename')->addError(
                        new FormError('Please upload an ISO file first')
                    );
                }
            }
            
            if ($fileSourceType === 'url') {
                if (!$filenameUrl) {
                    $form->get('Filename_url')->addError(
                        new FormError('Please type an URL')
                    );
                }
            }

            if ($fileSourceType === 'filename') {
                if (!$filename) {
                    $form->get('Filename')->addError(
                        new FormError('Please enter a filename')
                    );
                }
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