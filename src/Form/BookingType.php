<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class BookingType extends AbstractType
{
    
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->bookingRepopstory = $bookingRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
            ])
            ->add('reservedFor', ChoiceType::class, [
                'choices' => [
                    'User' => "user",
                    'Group' => "group"
                ],
                'empty_data' => 'User',
                'label' => "Type"
            ])

            ->addEventListener(FormEvents::PRE_SET_DATA, array($this, 'onPreSetOwner'))
            ->add('yearStart',ChoiceType::class, [
                'choices' => [
                    date('Y') => date('Y'),
                    date('Y') +1 => date('Y')+1,
                ],
                'mapped' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, array($this, 'onPreSetStartDate'))
            ->add('yearEnd',ChoiceType::class, [
                'choices' => [
                    date('Y') => date('Y'),
                    date('Y') +1 => date('Y')+1,
                ],
                'mapped' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, array($this, 'onPreSetEndDate'))
            ->add('submit', SubmitType::class)
        ;
    }

    public function addOwnerElement($form, $type = "user")
    {
        if ($type == "group"){
            $form->add('group', EntityType::class, [
                'class' => Group::class,
                'choice_label' => 'name',
            ]);
        }
        else {
            $form->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
            ]);
        }
    }

    public function addStartDateElement($form, $dateStart)
    {
        $form->add('monthStart',ChoiceType::class, [
            'choices' => [
                "01" => 1,
                "02" => 2,
                "03" => 3,
                "04" => 4,
                "05" => 5,
                "06" => 6,
                "07" => 7,
                "08" => 8,
                "09" => 9,
                "10" => 10,
                "11" => 11,
                "12" => 12,
            ],
            'mapped' => false,
            'label'=> false
        ]);
        $form->add('dayStart',ChoiceType::class, [
            'choices' => [
                "01" => 1,
                "02" => 2,
                "03" => 3,
                "04" => 4,
                "05" => 5,
                "06" => 6,
                "07" => 7,
                "08" => 8,
                "09" => 9,
                "10" => 10,
                "11" => 11,
                "12" => 12,
                "13" => 13,
                "14" => 14,
                "15" => 15,
                "16" => 16,
                "17" => 17,
                "18" => 18,
                "19" => 19,
                "20" => 20,
                "21" => 21,
                "22" => 22,
                "23" => 23,
                "24" => 24,
                "25" => 25,
                "26" => 26,
                "27" => 27,
                "28" => 28,
                "29" => 29,
                "30" => 30,
                "31" => 31
            ],
            'mapped' => false,
            'label'=> false
        ]);

        $form->add('hourStart',ChoiceType::class, [
            'choices' => [
                "00" => 0,
                "01" => 1,
                "02" => 2,
                "03" => 3,
                "04" => 4,
                "05" => 5,
                "06" => 6,
                "07" => 7,
                "08" => 8,
                "09" => 9,
                "10" => 10,
                "11" => 11,
                "12" => 12,
                "13" => 13,
                "14" => 14,
                "15" => 15,
                "16" => 16,
                "17" => 17,
                "18" => 18,
                "19" => 19,
                "20" => 20,
                "21" => 21,
                "22" => 22,
                "23" => 23,
            ],
            'mapped' => false,
            'label'=> false
        ]);
        $form->add('minuteStart',ChoiceType::class, [
            'choices' => [
                "00" => 0,
                "15" => 15,
                "30" => 30,
                "45" => 45,
            ],
            'mapped' => false,
            'label'=> false
        ]);
    }

    public function onPreSetStartDate(FormEvent $event) 
    {
        $form = $event->getForm();
        $data = $event->getData();

        $date = $form->get('yearStart')->getData();
        if ($form->get('yearStart')->getData() == null) {
            $date = ( new \DateTime() )->format('Y-m-d');
        }

        $this->addStartDateElement($form, $date);
    }

    public function addEndDateElement($form, $dateStart)
    {
        $form->add('monthEnd',ChoiceType::class, [
            'choices' => [
                "01" => 1,
                "02" => 2,
                "03" => 3,
                "04" => 4,
                "05" => 5,
                "06" => 6,
                "07" => 7,
                "08" => 8,
                "09" => 9,
                "10" => 10,
                "11" => 11,
                "12" => 12,
            ],
            'mapped' => false,
            'label'=> false
        ]);
        $form->add('dayEnd',ChoiceType::class, [
            'choices' => [
                "01" => 1,
                "02" => 2,
                "03" => 3,
                "04" => 4,
                "05" => 5,
                "06" => 6,
                "07" => 7,
                "08" => 8,
                "09" => 9,
                "10" => 10,
                "11" => 11,
                "12" => 12,
                "13" => 13,
                "14" => 14,
                "15" => 15,
                "16" => 16,
                "17" => 17,
                "18" => 18,
                "19" => 19,
                "20" => 20,
                "21" => 21,
                "22" => 22,
                "23" => 23,
                "24" => 24,
                "25" => 25,
                "26" => 26,
                "27" => 27,
                "28" => 28,
                "29" => 29,
                "30" => 30,
                "31" => 31,
            ],
            'mapped' => false,
            'label'=> false
        ]);

        $form->add('hourEnd',ChoiceType::class, [
            'choices' => [
                "00" => 0,
                "01" => 1,
                "02" => 2,
                "03" => 3,
                "04" => 4,
                "05" => 5,
                "06" => 6,
                "07" => 7,
                "08" => 8,
                "09" => 9,
                "10" => 10,
                "11" => 11,
                "12" => 12,
                "13" => 13,
                "14" => 14,
                "15" => 15,
                "16" => 16,
                "17" => 17,
                "18" => 18,
                "19" => 19,
                "20" => 20,
                "21" => 21,
                "22" => 22,
                "23" => 23,
            ],
            'mapped' => false,
            'label'=> false
        ]);
        $form->add('minuteEnd',ChoiceType::class, [
            'choices' => [
                "00" => 0,
                "15" => 15,
                "30" => 30,
                "45" => 45,
            ],
            'mapped' => false,
            'label'=> false
        ]);
    }

    public function onPreSetEndDate(FormEvent $event) 
    {
        $form = $event->getForm();
        $data = $event->getData();

        $date = $form->get('yearEnd')->getData();
        if ($form->get('yearEnd')->getData() == null) {
            $date = ( new \DateTime() )->format('Y-m-d');
        }

        $this->addEndDateElement($form, $date);
    }

    public function onPreSetOwner(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        $this->addOwnerElement($form, $form->get('reservedFor')->getData());
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
