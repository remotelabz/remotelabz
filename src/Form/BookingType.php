<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Repository\BookingRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;


class BookingType extends AbstractType implements DataMapperInterface
{
    
    public function __construct(BookingRepository $bookingRepository, UserRepository $userRepository, GroupRepository $groupRepository)
    {
        $this->bookingRepository = $bookingRepository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $booking = $builder->getData();

        if ($booking->getStartDate() == null) {
            $monthStartAtrr = function($choice, $key,$value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$value < (int)$today->format("m")) {
                    $disabled = true;
                }
                return $disabled ? ["disabled" => "disabled"] : [];
            };
            $dayStartAtrr = function($choice, $key, $value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$value < (int)$today->format("d")) {
                    $disabled = true;
                }
                return $disabled ? ["disabled" => "disabled"] : [];
            };

            $hourStartAtrr = function($choice, $key, $value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$today->format("i") <= 45) {
                    if ((int)$value < (int)$today->format("H")) {
                        $disabled = true;
                    }
                }
                else {
                    if ((int)$value < ((int)$today->format("H")+1)) {
                        $disabled = true;
                    }
                }

                return $disabled ? ["disabled" => "disabled"] : [];
            };

            $minuteStartAtrr = function($choice, $key, $value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$today->format("i") <= 45) {
                    if ((int)$value < (int)$today->format("i")) {
                        $disabled = true;
                    }
                }

                return $disabled ? ["disabled" => "disabled"] : [];
            };

            $monthEndAtrr = function($choice, $key, $value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$value < (int)$today->format("m")) {
                    $disabled = true;
                }
                return $disabled ? ["disabled" => "disabled"] : [];
            };
            $dayEndAtrr = function($choice, $key, $value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$value < (int)$today->format("d")) {
                    $disabled = true;
                }
                return $disabled ? ["disabled" => "disabled"] : [];
            };

            $hourEndAtrr = function($choice, $key, $value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$today->format("i") < 30) {
                    if ((int)$value < (int)$today->format("H")) {
                        $disabled = true;
                    }
                }
                else {
                    if ((int)$value < ((int)$today->format("H") +1)) {
                        $disabled = true;
                    }
                }

                return $disabled ? ["disabled" => "disabled"] : [];
            };

            $minuteEndAtrr = function($choice, $key, $value) {
                $today = new \DateTime("now");
                $disabled = false;
                if ((int)$today->format("i") < 30) {
                    if ((int)$value < ((int)$today->format("i") +15)) {
                        $disabled = true;
                    }
                }
                else if ((int)$today->format("i") >= 45) {
                    if ((int)$value < 15) {
                        $disabled = true;
                    }
                }

                return $disabled ? ["disabled" => "disabled"] : [];
            };
        }
        else {
            $monthStartAtrr = $dayStartAtrr = $hourStartAtrr = $minuteStartAtrr = $monthEndAtrr = $dayEndAtrr = $hourEndAtrr = $minuteEndAtrr = function($choice, $key,$value) {
                return [];
            };
        }
        $builder
            ->add('name', TextType::class)
            ->add('reservedFor', ChoiceType::class, [
                'choices' => [
                    'User' => "user",
                    'Group' => "group"
                ],
                'label' => "Type"
            ])

            ->addEventListener(FormEvents::PRE_SET_DATA, array($this, 'onPreSetOwner'))
            ->addEventListener(FormEvents::PRE_SUBMIT, array($this, 'onPreSubmit'))
            ->add('yearStart',ChoiceType::class, [
                'choices' => [
                    date('Y') => date('Y'),
                    date('Y') +1 => date('Y')+1,
                ],
                'mapped' => false
            ])


            ->add('monthStart',ChoiceType::class, [
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
                'choice_attr' => $monthStartAtrr,
                'mapped' => false,
                'label'=> false
            ])
            ->add('dayStart',ChoiceType::class, [
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
                'choice_attr' => $dayStartAtrr,
                'mapped' => false,
                'label'=> false
            ])
    
            ->add('hourStart',ChoiceType::class, [
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
                'choice_attr' => $hourStartAtrr,
                'mapped' => false,
                'label'=> false
            ])
            ->add('minuteStart',ChoiceType::class, [
                'choices' => [
                    "00" => 0,
                    "15" => 15,
                    "30" => 30,
                    "45" => 45,
                ],
                'choice_attr' => $minuteStartAtrr,
                'mapped' => false,
                'label'=> false
            ])
            ->add('yearEnd',ChoiceType::class, [
                'choices' => [
                    date('Y') => date('Y'),
                    date('Y') +1 => date('Y')+1,
                ],
                'mapped' => false,
            ])
            ->add('monthEnd',ChoiceType::class, [
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
                'choice_attr' => $monthEndAtrr,
                'mapped' => false,
                'label'=> false
            ])
            ->add('dayEnd',ChoiceType::class, [
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
                'choice_attr' => $dayEndAtrr,
                'mapped' => false,
                'label'=> false
            ])
    
            ->add('hourEnd',ChoiceType::class, [
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
                'choice_attr' => $hourEndAtrr,
                'mapped' => false,
                'label'=> false
            ])
            ->add('minuteEnd',ChoiceType::class, [
                'choices' => [
                    "00" => 0,
                    "15" => 15,
                    "30" => 30,
                    "45" => 45,
                ],
                'choice_attr' => $minuteEndAtrr,
                'mapped' => false,
                'label'=> false
            ])
            ->setDataMapper($this)
            ->add('submit', SubmitType::class)
        ;
    }

    public function addOwnerElement($form, $type = "user")
    {
        if ($type == "group"){
            $label = "Group";
            $owners = $this->groupRepository->findAll();
        }
        else {
            $label = "User";
            $owners = $this->userRepository->findAll();
        }
        $choices = [];
        foreach ($owners as $owner) {
            $choices[$owner->getName()] = $owner->getUuid();
        }

        $form->add('owner', ChoiceType::class, [
            'choices' => $choices,
            'label' => $label,
            'mapped' => false
        ]);
    }

    /**
     * @param Booking|null $viewData
     */
    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null == $viewData->getName()) {
            return;
        }

        // invalid data type
        if (!$viewData instanceof Booking) {
            throw new UnexpectedTypeException($viewData, Booking::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);


        // initialize form field values
        if(isset($forms["name"])) {
            $forms["name"]->setData($viewData->getName());
        }
        if(isset($forms["reservedFor"])) {
            $forms["reservedFor"]->setData($viewData->getReservedFor());
        }
        if(isset($forms["owner"])) {
            $forms["owner"]->setData($viewData->getOwner()->getUuid());
        }


        if(isset($forms["yearStart"])) {
            $forms['yearStart']->setData((int)$viewData->getStartDate()->format("Y"));
        }
        if(isset($forms["monthStart"])) {
            $forms['monthStart']->setData((int)$viewData->getStartDate()->format("m"));
        }
        if(isset($forms["dayStart"])) {
            $forms['dayStart']->setData((int)$viewData->getStartDate()->format("d"));
        }
        if(isset($forms["hourStart"])) {
            $forms['hourStart']->setData((int)$viewData->getStartDate()->format("H"));
        }
        if(isset($forms["minuteStart"])) {
            $forms['minuteStart']->setData((int)$viewData->getStartDate()->format("i"));
        }
        
        if(isset($forms["yearEnd"])) {
            $forms['yearEnd']->setData((int)$viewData->getEndDate()->format("Y"));
        }
        if(isset($forms["monthEnd"])) {
            $forms['monthEnd']->setData((int)$viewData->getEndDate()->format("m"));
        }
        if(isset($forms["dayEnd"])) {
            $forms['dayEnd']->setData((int)$viewData->getEndDate()->format("d"));
        }
        if(isset($forms["hourEnd"])) {
            $forms['hourEnd']->setData((int)$viewData->getEndDate()->format("H"));
        }
        if(isset($forms["minuteEnd"])) {
            $forms['minuteEnd']->setData((int)$viewData->getEndDate()->format("i"));
        }

    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $viewData->setName($forms['name']->getData());
        $viewData->setReservedFor($forms['reservedFor']->getData());
        $dateStart = new \DateTime($forms['yearStart']->getData()."-".$forms['monthStart']->getData()."-".$forms['dayStart']->getData()." ".$forms['hourStart']->getData().":".$forms['minuteStart']->getData());
        $dateEnd = new \DateTime($forms['yearEnd']->getData()."-".$forms['monthEnd']->getData()."-".$forms['dayEnd']->getData()." ".$forms['hourEnd']->getData().":".$forms['minuteEnd']->getData());
        $viewData->setStartDate($dateStart);
        $viewData->setEndDate($dateEnd);
    }
 

    public function onPreSetOwner(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        $reservedFor = "user";
        if ($data->getReservedFor() != null) {
            $reservedFor = $data->getReservedFor();
        }

        $this->addOwnerElement($form, $reservedFor);
    }

    public function onPreSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        $this->addOwnerElement($form, $data['reservedFor']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
            "allow_extra_fields" => true
        ]);
    }
}