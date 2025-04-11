<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Entity\Group;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use App\Repository\LabRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

class BookingController extends Controller
{
    public $labRepository;
    public $labInstanceRepository;
    public $deviceInstanceRepository;
    public $bookingRepository;
    public $userRepository;
    public $groupRepository;

    public function __construct(
        LabRepository $labRepository, 
        BookingRepository $bookingRepository, 
        LabInstanceRepository $labInstanceRepository, 
        DeviceInstanceRepository $deviceInstanceRepository, 
        UserRepository $userRepository, 
        GroupRepository $groupRepository,
        EntityManagerInterface $entityManager
    )
    {
        $this->labRepository = $labRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->bookingRepository = $bookingRepository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/bookings', name: 'api_bookings')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    #[Route(path: '/bookings', name: 'bookings')]
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $labs = $this->labRepository->findByBookings($search);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($labs, 200, [], ['api_get_lab']);
        }

        return $this->render('booking/index.html.twig', [
            'labs' => $labs,
            'search' => $search
        ]);
    }

    
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    #[Route(path: '/bookings/{id<\d+>}', name: 'show_booking')]
    public function showAction(Request $request, int $id)
    {
        $booking = $this->bookingRepository->find($id);
        $user = $this->getUser();
        $canSee = false;
        $canEdit = false;
        if ($user->isAdministrator() || $user == $booking->getAuthor()) {
            $canSee = true;
            $canEdit = true;
        }

        if ($booking->getOwner() instanceof Group) {
            if ($user->isMemberOf($booking->getOwner())) {
                $canSee = true;
            }
            if($booking->getOwner()->isElevatedUser($user)) {
                $canSee = true;
                $canEdit = true;
            }
        }
        else {
            foreach ($user->getGroups() as $groupUser) {
                $group = $groupUser->getGroup();
                if ($group->isElevatedUser($user) && $booking->getOwner()->isMemberOf($group) && $user != $booking->getOwner()) {
                    $canSee = true;
                    $canEdit = true;
                }
            }
            if ($user == $booking->getOwner()) {
                $canSee = true;
            }
        }
        if ($canSee == false) {
            throw new AccessDeniedHttpException("Access denied.");
        }
        $bookingObject = [
            "id" => $booking->getId(),
            "name" => $booking->getName(),
            "lab" => $booking->getLab(),
            "author" => $booking->getAuthor(),
            "owner" => $booking->getOwner(),
            "bookedFor" => $booking->getReservedFor(),
            "startDate" => $booking->getStartDate(),
            "endDate" => $booking->getEndDate()
        ];

        return $this->render("booking/view.html.twig", [
            //'lab' => $lab,
            'booking' => $booking,
            "canEdit" => $canEdit
        ]);

    }

    
	#[Get('/api/bookings/lab/{id<\d+>}', name: 'api_show_lab_bookings')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    #[Route(path: '/bookings/lab/{id<\d+>}', name: 'show_lab_bookings')]
    public function showLabBookings(Request $request, int $id)
    {
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        }

        if ($lab->getVirtuality() == 1) {
            throw new BadRequestHttpException("Lab ".$lab->getId()." is virtual. Making a reservation is not possible.");
        }

        $user = $this->getUser();

        $bookings = $this->bookingRepository->findBy(["lab"=>$lab],['startDate'=>'ASC']);
        if ('json' === $request->getRequestFormat()) {
            //return $this->json($lab, 200, [], ["apu_show_lab_bookings"]);
        }

        $currentDate = new \DateTime();

        return $this->render("booking/showLab.html.twig", [
            'lab' => $lab,
            'bookings' => $bookings,
            'currentDate' => $currentDate
        ]);
    }

    
	#[Post('/api/bookings/lab/{id<\d+>}', name: 'api_new_booking')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    #[Route(path: '/bookings/lab/{id<\d+>}/new', name: 'new_booking', methods: ['GET', 'POST'])]
    public function newAction(Request $request, int $id)
    {
        $booking = new Booking();
        $bookingForm = $this->createForm(BookingType::class, $booking);
        $bookingForm->handleRequest($request);
        $user = $this->getUser();

        $lab = $this->labRepository->find($id);
        if ($lab->getVirtuality() == 0) {
            if ($bookingForm->isSubmitted() && $bookingForm->isValid()) {
                $dateStart = new \DateTime($bookingForm["dayStart"]->getData()."-".$bookingForm['monthStart']->getData()."-".$bookingForm['yearStart']->getData()." ".$bookingForm["hourStart"]->getData().":".$bookingForm["minuteStart"]->getData());
                $dateEnd = new \DateTime($bookingForm["dayEnd"]->getData()."-".$bookingForm['monthEnd']->getData()."-".$bookingForm['yearEnd']->getData()." ".$bookingForm["hourEnd"]->getData().":".$bookingForm["minuteEnd"]->getData());
                $isValid = $this->checkDatesValidation($dateStart, $dateEnd, $lab);
                if ($isValid == 0) {
                    if ($user->getHighestRole() != "ROLE_USER") {
                        if ($bookingForm['reservedFor']->getData() == "group") {
                            $owner = $this->groupRepository->findOneBy(['uuid'=> $bookingForm['owner']->getData()]);
                        }
                        else {
                            $owner = $this->userRepository->findOneBy(['uuid'=> $bookingForm['owner']->getData()]);
                        }
                        $reservedFor = $bookingForm['reservedFor']->getData();
                    }
                    else {
                        $owner = $user;
                        $reservedFor = "user";
                    }
                    
                    if ($owner && (($reservedFor == "group" && $owner instanceof Group) || ($reservedFor == "user" && $owner instanceof User) )) {
                        $booking = $bookingForm->getData();
                        $booking->setName($bookingForm['name']->getData());
                        $booking->setAuthor($this->getUser());
                        $booking->setReservedFor($reservedFor);
                        if ($owner instanceof Group) {
                            $booking->setGroup($owner);
                        }
                        else {
                            $booking->setUser($owner);
                        }
                        $booking->setLab($lab);
                        $booking->setStartDate($dateStart);
                        $booking->setEndDate($dateEnd);
                        $entityManager = $this->entityManager;
                        $entityManager->persist($booking);
                        $entityManager->flush();
                        if ('json' === $request->getRequestFormat()) {
                            return $this->json($booking, 201, [], ['api_get_booking']);
                        }
                        return $this->redirectToRoute('show_lab_bookings', ['id' => $lab->getId()]);
                    }
                    else {
                        $this->addFlash("danger", "The owner does not exist.");
                    }
                    
                }
                else {
                    switch ($isValid) {
                        case 1:
                            $this->addFlash("danger", "The starting date of the booking must precede the ending date.");
                            break;
                        case 2:
                            $this->addFlash("danger", "The starting date cannot precede the current time.");
                            break;
                        case 3:
                            $this->addFlash("danger", "The booking cannot start during another booking");
                            break;
                        case 4:
                            $this->addFlash("danger", "Another booking starts during the choosen period");
                            break;
                        case 5:
                            $this->addFlash("danger", "A booking cannot last more than 4 weeks");
                    }
                     //throw new BadRequestHttpException('The dates are not valid'); 
                }
                
            }
        }
        if ($request->getContentType() === 'json') {
            $booking = json_decode($request->getContent(), true);
            $bookingForm->submit($booking);
        }

        return $this->render('booking/new.html.twig', [
            'bookingForm' => $bookingForm->createView(),
            'lab' => $lab,
        ]);
    }

    
	#[Put('/api/bookings/{id<\d+>}', name: 'api_edit_booking')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    #[Route(path: '/bookings/{id<\d+>}/edit', name: 'edit_booking')]
    public function editAction(Request $request, int $id)
    {
        if (!$booking = $this->bookingRepository->find($id)) {
            throw new NotFoundHttpException("Booking " . $id . " does not exist.");
        } 
        $oldStartDate = $booking->getStartDate();
        $oldEndDate = $booking->getEndDate();
        $user = $this->getUser();
        $canEdit = false;
        if ($user->isAdministrator() || $user == $booking->getAuthor()) {
            $canEdit = true;
        }

        if ($booking->getOwner() instanceof Group ) {
            if($booking->getOwner()->isElevatedUser($user)) {
                $canEdit = true;
            }
        }
        else {
            foreach ($user->getGroups() as $groupUser) {
                $group = $groupUser->getGroup();
                if ($group->isElevatedUser($user) && $booking->getOwner()->isMemberOf($group) && $user != $booking->getOwner()) {
                    $canEdit = true;
                }
            }
        }
        if ($canEdit == false) {
            throw new AccessDeniedHttpException("Access denied.");
        }

        $bookingForm = $this->createForm(BookingType::class, $booking);
        $bookingForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $booking = json_decode($request->getContent(), true);
            $bookingForm->submit($booking);
        }

        $lab = $booking->getLab();
        if ($lab->getVirtuality() == 0) {
            if ($bookingForm->isSubmitted() && $bookingForm->isValid()) {
                $dateStart = new \DateTime($bookingForm["dayStart"]->getData()."-".$bookingForm['monthStart']->getData()."-".$bookingForm['yearStart']->getData()." ".$bookingForm["hourStart"]->getData().":".$bookingForm["minuteStart"]->getData());
                $dateEnd = new \DateTime($bookingForm["dayEnd"]->getData()."-".$bookingForm['monthEnd']->getData()."-".$bookingForm['yearEnd']->getData()." ".$bookingForm["hourEnd"]->getData().":".$bookingForm["minuteEnd"]->getData());
                if ($dateStart == $oldStartDate && $dateEnd == $oldEndDate) {
                    $isValid = 0;
                }
                else {
                    $isValid = $this->checkDatesValidation($dateStart, $dateEnd, $lab, $booking->getId());
                }
                if ($isValid == 0) {
                    if ($bookingForm['reservedFor']->getData() == "group") {
                        $owner = $this->groupRepository->findOneBy(['uuid'=> $bookingForm['owner']->getData()]);
                    }
                    else {
                        $owner = $this->userRepository->findOneBy(['uuid'=> $bookingForm['owner']->getData()]);
                    }
                    if ($owner && (($bookingForm['reservedFor']->getData() == "group" && $owner instanceof Group) || ($bookingForm['reservedFor']->getData() == "user" && $owner instanceof User) )) {
                        $booking = $bookingForm->getData();
                        $booking->setName($bookingForm['name']->getData());
                        $booking->setAuthor($this->getUser());
                        $booking->setLab($lab);
                        if ($user->getHighestRole() != "ROLE_USER") {
                            $booking->setReservedFor($bookingForm['reservedFor']->getData());
                            if ($owner instanceof Group) {
                                $booking->setGroup($owner);
                                $booking->setUser(null);
                            }
                            else {
                                $booking->setUser($owner);
                                $booking->setGroup(null);
                            }
                        }
                        $booking->setStartDate(new \DateTime($bookingForm["dayStart"]->getData()."-".$bookingForm['monthStart']->getData()."-".$bookingForm['yearStart']->getData()." ".$bookingForm["hourStart"]->getData().":".$bookingForm["minuteStart"]->getData()));
                        $booking->setEndDate(new \DateTime($bookingForm["dayEnd"]->getData()."-".$bookingForm['monthEnd']->getData()."-".$bookingForm['yearEnd']->getData()." ".$bookingForm["hourEnd"]->getData().":".$bookingForm["minuteEnd"]->getData()));
                        $entityManager = $this->entityManager;
                        $entityManager->persist($booking);
                        $entityManager->flush();
        
                        if ('json' === $request->getRequestFormat()) {
                            return $this->json($booking, 201, [], ['api_get_booking']);
                        }
                        return $this->redirectToRoute('show_lab_bookings', ['id' => $lab->getId()]);
                    }
                    else {
                        $this->addFlash("danger", "The owner does not exist.");
                    }
                }
                else {
                    switch ($isValid) {
                        case 1:
                            $this->addFlash("danger", "The starting date of the booking must precede the ending date.");
                            break;
                        case 2:
                            $this->addFlash("danger", "The starting date cannot precede the current time.");
                            break;
                        case 3:
                            $this->addFlash("danger", "The booking cannot start during another booking");
                            break;
                        case 4:
                            $this->addFlash("danger", "Another booking starts during the choosen period");
                            break;
                        case 5:
                            $this->addFlash("danger", "A booking cannot last more than 4 weeks");
                    }
                     //throw new BadRequestHttpException('The dates are not valid'); 
                }
                
            }
        }

        return $this->render('booking/new.html.twig', [
            'bookingForm' => $bookingForm->createView(),
            'booking' => $booking,
            'lab' => $lab,
        ]);
    }

    
	#[Delete('/api/bookings/{id<\d+>}', name: 'api_delete_booking')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    #[Route(path: '/bookings/{id<\d+>}/delete', name: 'delete_booking', methods: 'GET')]
    public function deleteAction(Request $request, int $id)
    {
        if (!$booking = $this->bookingRepository->find($id)) {
            throw new NotFoundHttpException("Booking " . $id . " does not exist.");
        }

        $user = $this->getUser();
        $canEdit = false;
        if ($user->isAdministrator() || $user == $booking->getAuthor()) {
            $canEdit = true;
        }

        if ($booking->getOwner() instanceof Group ) {
            if($booking->getOwner()->isElevatedUser($user)) {
                $canEdit = true;
            }
        }
        else {
            foreach ($user->getGroups() as $groupUser) {
                $group = $groupUser->getGroup();
                if ($group->isElevatedUser($user) && $booking->getOwner()->isMemberOf($group) && $user != $booking->getOwner()) {
                    $canEdit = true;
                }
            }
        }
        if ($canEdit == false) {
            throw new AccessDeniedHttpException("Access denied.");
        }
        $lab = $booking->getLab();

        $entityManager = $this->entityManager;
        $entityManager->remove($booking);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('show_lab_bookings', ['id' => $lab->getId()]);
    }

    
	#[Delete('/api/bookings/by_uuid/{uuid}', name: 'api_delete_booking_by_uuid', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function deleteActionByUuid(Request $request, string $uuid)
    {
        if (!$booking = $this->bookingRepository->findOneBy(["uuid" =>$uuid])) {
            throw new NotFoundHttpException("Booking " . $booking->getId(). " does not exist.");
        }
        if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {
            throw new AccessDeniedHttpException("Access denied.");
        }
        $lab = $booking->getLab();
        if (!$labInstance = $this->labInstanceRepository->findBy(["lab" => $lab])) {
            $entityManager = $this->entityManager;
            $entityManager->remove($booking);
            $entityManager->flush();
        }
        else {
            throw new BadRequestHttpExeception("An instance of the lab exists.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('show_lab_bookings', ['id' => $lab->getId()]);
    }

    private function checkDatesValidation($dateStart, $dateEnd, $lab, $newBooking = false) {
        $now = new \DateTime("now");
        if ($dateEnd <= $dateStart) {
            return 1;
        }
        if ($dateStart->format("Y-m-d H:i") < $now->format("Y-m-d H:i")) {
            return 2;
        }
        if ($dateStart->diff($dateEnd)->format("%a") > 28) {
            return 5;
        } 
        $bookings = $this->bookingRepository->findBy(["lab"=>$lab],['startDate'=>'ASC']);
        foreach ($bookings as $booking) {
            if ($newBooking == false || $newBooking != $booking->getId()) {
                if ($booking->getStartDate() <= $dateStart && $dateStart < $booking->getEndDate()) {
                    return 3;
                }
                else if ($dateStart <= $booking->getStartDate() &&  $booking->getStartDate() < $dateEnd) {
                    return 4;
                }
            }
            
        }
        return 0;
        
    }

    
	#[Get('/api/bookings/old', name: 'api_get_old_bookings')]
    public function getOldBookings(Request $request) {
        if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {
            throw new AccessDeniedHttpException("Access denied.");
        }

        $bookings = $this->bookingRepository->findOldBookings(new \DateTime());
        foreach ($bookings as $key => $booking) {
            if (isset($booking['lab_instance_uuid'])) {
                $bookings[$key]['device_instances'] = [];
                $labInstance = $this->labInstanceRepository->findOneBy(['uuid' =>$booking['lab_instance_uuid']]);
                $deviceInstances = $labInstance->getDeviceInstances();
                foreach ($deviceInstances as $deviceInstance) {
                    $deviceInstance_json["device_instance_uuid"] = $deviceInstance->getUuid();
                    $deviceInstance_json["device_instance_id"] = $deviceInstance->getId();
                    array_push($bookings[$key]['device_instances'], $deviceInstance_json);
                }
            }
        }
        if ('json' === $request->getRequestFormat()) {
            return $this->json($bookings, 200, [], []);
        }

    }

    
	#[Post('/api/bookings/owner', name: 'api_owner_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]

    public function onReservedForChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $user = $this->getUser();
        $reservedFor = $data["reservedFor"];

        if ($user->getHighestRole() == "ROLE_USER") {
            $choices[$user->getName()] = $user->getUuid();
            $disabled = true;
            $label = "User";
        }
        if ($reservedFor == "group") {
            if ($user->getHighestRole() == "ROLE_USER") {
                $owners = [];
            }
            else if ($user->getHighestRole() == "ROLE_TEACHER" || $user->getHighestRole() == "ROLE_TEACHER_EDITOR") {
                $owners = [];
                foreach ($user->getGroupsInfo() as $group) {
                    if ($group->isElevatedUser($user)) {
                        array_push($owners, $group);
                    }
                }
            }
            else if ($user->isAdministrator()) {
                $owners = $this->groupRepository->findAll();
            }
            else {
                throw new AccessDeniedHttpException("Access denied.");
            }
            
        }
        else {
            if ($user->getHighestRole() == "ROLE_USER") {
                $owners = [];
                array_push($owners, $user);
            }
            else if ($user->getHighestRole() == "ROLE_TEACHER" || $user->getHighestRole() == "ROLE_TEACHER_EDITOR") {
                $owners = [];
                array_push($owners, $user);
                foreach ($user->getGroupsInfo() as $group) {
                    if ($group->isElevatedUser($user)) {
                        foreach ($group->getUsers() as $member) {
                            if (!in_array($member, $owners)) {
                                array_push($owners, $member);
                            }
                        }
                    }
                }
            }
            else if ($user->isAdministrator()) {
                $owners = $this->userRepository->findAll();
            }
            else {
                throw new AccessDeniedHttpException("Access denied.");
            }
        }
        $ownerList = [];
        foreach ($owners as $owner) {
            array_push($ownerList,[
                "uuid" => $owner->getUuid(),
                "name" => $owner->getName()
            ]);
        }
        return $this->json($ownerList, 200, [], []);
    }

    
	#[Post('/api/bookings/yearStart', name: 'api_year_start_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onYearStartChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        if ($data['dates']['dateTimeStart']['year'] < date('Y')) {
            //error
        }
        $today = new \DateTime("now");
        $year = $data['dates']['dateTimeStart']['year'];
        $choices = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthName = "";
            if ($i < 10) { $monthName = "0";}
            $monthName .= (string)$i;
            $choices[$monthName] = ["name" => $monthName, "activation" => "enabled", "value" => $i];
            if ($i == 4 || $i == 6 || $i == 9 || $i == 11) {
                $days = 30;
            }
            else if ($i == 2) {
                if (($year % 4 == 0 && $year % 100 != 0) || ($year % 4 == 0 && $year % 400 == 0)) {
                    $days = 29;
                }
                else {
                    $days = 28;
                }
            }
            else {
                $days = 31;
            }
            for ($j = 1; $j <= $days; $j++) {
                $dayName = "";
                if ($j < 10) { $dayName = "0";}
                $dayName .= (string)$j;
                $choices[$monthName]["days"][$dayName] = ["name" => $dayName, "activation" => "enabled", "value" => $j];
                for ($k = 0; $k <= 23; $k++) {
                    $hourName = "";
                    if ($k < 10) { $hourName = "0";}
                    $hourName .= (string)$k;
                    $choices[$monthName]["days"][$dayName]["hours"][$hourName] = ["name" => $hourName, "activation" => "enabled", "value" => $k];
                    for ($l = 0; $l <= 45; $l+=15) {
                        $minuteName = "";
                        if ($l < 10) { $minuteName = "0";}
                        $minuteName .= (string)$l;
                        $dateTest = new \DateTime($year."-".$i."-".$j." ".$k.":".$l);
                        if ($dateTest < $today) {
                            $activation = "disabled";
                        }
                        else {
                            $activation = "enabled";
                        }
                        $choices[$monthName]["days"][$dayName]["hours"][$hourName]["minutes"][$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $l];
                    }
                }
            }
        }

        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }
        foreach ($bookings as $booking) {
            if ($booking != $oldBooking) {
                foreach ($choices as $choice) {
                    $monthTest = new \DateTime($year."-".$choice["value"]);
                    if ($booking->getStartDate()->format("Y-m") <= $monthTest->format("Y-m") && $monthTest->format("Y-m") <= $booking->getEndDate()->format("Y-m")) {
                        foreach ($choice["days"] as $day) {
                            $dayTest = new \DateTime($monthTest->format("Y-m")."-".$day["value"]); 
                            if ($booking->getStartDate()->format("Y-m-d") <= $dayTest->format("Y-m-d") && $dayTest->format("Y-m-d") <= $booking->getEndDate()->format("Y-m-d")) {
                                foreach ($day["hours"] as $hour) {
                                    $hourTest = new \DateTime($dayTest->format("Y-m-d")." ".$hour["value"].":0");
                                    if ($booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H") && $hourTest->format("Y-m-d H") <= $booking->getEndDate()->format("Y-m-d H")) {
                                        foreach ($hour["minutes"] as $minute) {
                                            $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                                            if ($booking->getStartDate()->format("Y-m-d H:i") <= $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") < $booking->getEndDate()->format("Y-m-d H:i")) {
                                                $choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                            }
                                            else if ($booking->getStartDate()->format("Y-m-d H") == $minuteTest->format("Y-m-d H") && $booking->getStartDate()->format("Y-m-d H:i") > $minuteTest->format("Y-m-d H:i") && $minuteTest->diff($booking->getStartDate())->format("%i") <= 15) {
                                                $choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                            }
                                        }
                                    }
                                    else if ($booking->getStartDate()->format("Y-m-d") == $hourTest->format("Y-m-d") && $booking->getStartDate()->format("Y-m-d H") > $hourTest->format("Y-m-d H") && $hourTest->diff($booking->getStartDate())->format("%H") == 1) { 
                                        $bookingTest = new \DateTime($hourTest->format("Y-m-d")." ".($hour["value"]+1).":0");
                                        if ($booking->getStartDate() == $bookingTest) {
                                            foreach ($hour["minutes"] as $minute) {
                                                if ($minute["value"] >= 45) {
                                                    $choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                                }
                                            }
                                        }
                                    }
                                    
                                }
                            }
                            else if ($booking->getStartDate()->format("Y-m") == $dayTest->format("Y-m") && $booking->getStartDate()->format("Y-m-d") > $dayTest->format("Y-m-d") && $dayTest->diff($booking->getStartDate())->format("%d") == 1) {
                                $bookingTest = new \DateTime($dayTest->format("Y-m")."-".($day["value"]+1)." 0:0");
                                if ($bookingTest == $booking->getStartDate()) {
                                    foreach ($day["hours"] as $hour) {
                                        if ($hour["value"] >= 23) {
                                            foreach ($hour["minutes"] as $minute) {
                                                if ($minute["value"] >= 45) {
                                                    $choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }  
        }

        foreach ($choices as $choice) {
            $disabledMonth = true;
            foreach ($choice["days"] as $day) {
                $disabledDay = true;
                foreach ($day["hours"] as $hour) {
                    $disabledHour = true;
                    foreach ($hour["minutes"] as $minute) {
                        if ($minute["activation"] == "enabled") {
                            $disabledHour = false;
                        }
                    }
                    if ($disabledHour == false) {
                        $choices[$choice["name"]]["days"][$day["name"]]['hours'][$hour["name"]]['activation'] = "enabled";
                        $hour["activation"] = "enabled";
                    }
                    else {
                        $choices[$choice["name"]]["days"][$day["name"]]['hours'][$hour["name"]]['activation'] = "disabled";
                        $hour["activation"] = "disabled";
                    }
                    if ($hour["activation"] == "enabled") {
                        $disabledDay = false;
                    }
                    usort($choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
                }
                if ($disabledDay == false) {
                    $choices[$choice["name"]]["days"][$day["name"]]['activation'] = "enabled";
                    $day['activation'] = "enabled";
                }
                else {
                    $choices[$choice["name"]]["days"][$day["name"]]['activation'] = "disabled";
                    $day['activation'] = "disabled";
                }
                if ($day["activation"] == "enabled") {
                    $disabledMonth = false;
                }
                usort($choices[$choice["name"]]["days"][$day["name"]]["hours"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
            }
            if ($disabledMonth == false) {
                $choices[$choice["name"]]['activation'] = "enabled";
            }
            else {
                $choices[$choice["name"]]['activation'] = "disabled";
            }
            usort($choices[$choice["name"]]["days"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        
        return $this->json($choices);
    }

    
	#[Post('/api/bookings/monthStart', name: 'api_month_start_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onMonthStartChange(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $today = new \DateTime("now"); 

        $choices = [];
        $month = $data['dates']['dateTimeStart']['month'];
        $year = $data['dates']['dateTimeStart']['year'];
        if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
            $days = 30;
        }
        else if ($month == 2) {
            if (($year % 4 == 0 && $year % 100 != 0) || ($year % 4 == 0 && $year % 400 == 0)) {
                $days = 29;
            }
            else {
                $days = 28;
            }
        }
        else {
            $days = 31;
        }

        for ($i = 1; $i <= $days; $i++) {
            $dayName = "";
            if ($i < 10) { $dayName = "0";}
            $dayName .= (string)$i;
            $choices[$dayName] = ["name" => $dayName, "activation" => "enabled", "value" => $i];
            for ($j = 0; $j <= 23; $j++) {
                $hourName = "";
                if ($j < 10) { $hourName = "0";}
                $hourName .= (string)$j;
                $choices[$dayName]["hours"][$hourName] = ["name" => $hourName, "activation" => "enabled", "value" => $j];
                for ($k = 0; $k <= 45; $k+=15) {
                    $minuteName = "";
                    if ($k < 10) { $minuteName = "0";}
                    $minuteName .= (string)$k;
                    $dateTest = new \DateTime($year."-".$month."-".$i." ".$j.":".$k);
                    if ($dateTest < $today) {
                        $activation = "disabled";
                    }
                    else {
                        $activation = "enabled";
                    }
                    $choices[$dayName]["hours"][$hourName]["minutes"][$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $k];
                }
            }
        }

        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }

        foreach ($bookings as $booking) {
            if ($oldBooking != $booking) {
                foreach ($choices as $choice) {
                    $dayTest = new \DateTime($year."-".$month."-".$choice["value"]); 
                    if ($booking->getStartDate()->format("Y-m-d") <= $dayTest->format("Y-m-d") && $dayTest->format("Y-m-d") <= $booking->getEndDate()->format("Y-m-d")) {
                        foreach ($choice["hours"] as $hour) {
                            $hourTest = new \DateTime($dayTest->format("Y-m-d")." ".$hour["value"].":0");
                            if ($booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H") && $hourTest->format("Y-m-d H") <= $booking->getEndDate()->format("Y-m-d H")) {
                                foreach ($hour["minutes"] as $minute) {
                                    $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                                    if ($booking->getStartDate()->format("Y-m-d H:i") <= $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") < $booking->getEndDate()->format("Y-m-d H:i")) {
                                        $choices[$choice["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                    }
                                    else if ($booking->getStartDate()->format("Y-m-d H") == $minuteTest->format("Y-m-d H") && $booking->getStartDate()->format("Y-m-d H:i") > $minuteTest->format("Y-m-d H:i") && $minuteTest->diff($booking->getStartDate())->format("%i") <= 15) {
                                        $choices[$choice["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                    }
                                }
                            }
                            else if ($booking->getStartDate()->format("Y-m-d") == $hourTest->format("Y-m-d") && $booking->getStartDate()->format("Y-m-d H") > $hourTest->format("Y-m-d H") && $hourTest->diff($booking->getStartDate())->format("%H") == 1) { 
                                $bookingTest = new \DateTime($hourTest->format("Y-m-d")." ".($hour["value"]+1).":0");
                                if ($booking->getStartDate() == $bookingTest) {
                                    foreach ($hour["minutes"] as $minute) {
                                        if ($minute["value"] >= 45) {
                                            $choices[$choice["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                        }
                                    }
                                }
                            }
                            
                        }
                    }
                }
            }  
        }

        foreach ($choices as $choice) {
            $disabledDay = true;
            foreach ($choice["hours"] as $hour) {
                $disabledHour = true;
                foreach ($hour["minutes"] as $minute) {
                    if ($minute["activation"] == "enabled") {
                        $disabledHour = false;
                    }
                }
                if ($disabledHour == false) {
                    $choices[$choice["name"]]['hours'][$hour["name"]]['activation'] = "enabled";
                    $hour["activation"] = "enabled";
                }
                else {
                    $choices[$choice["name"]]['hours'][$hour["name"]]['activation'] = "disabled";
                    $hour["activation"] = "disabled";
                }
                if ($hour["activation"] == "enabled") {
                    $disabledDay = false;
                }
                usort($choices[$choice["name"]]["hours"][$hour["name"]]["minutes"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
            }
            if ($disabledDay == false) {
                $choices[$choice["name"]]['activation'] = "enabled";
            }
            else {
                $choices[$choice["name"]]['activation'] = "disabled";
            }
            usort($choices[$choice["name"]]["hours"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        return $this->json($choices);
    }

    
	#[Post('/api/bookings/dayStart', name: 'api_day_start_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onDayStartChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("now");
        $choices = [];
        $day = $data['dates']['dateTimeStart']['day'];
        $month = $data['dates']['dateTimeStart']['month'];
        $year = $data['dates']['dateTimeStart']['year'];

        for ($i = 0; $i <= 23; $i++) {
            $hourName = "";
            if ($i < 10) { $hourName = "0";}
            $hourName .= (string)$i;
            $choices[$hourName] = ["name" => $hourName, "activation" => "enabled", "value" => $i];
            for ($j = 0; $j <= 45; $j+=15) {
                $minuteName = "";
                if ($j < 10) { $minuteName = "0";}
                $minuteName .= (string)$j;
                $dateTest = new \DateTime($year."-".$month."-".$day." ".$i.":".$j);
                if ($dateTest < $today) {
                    $activation = "disabled";
                }
                else {
                    $activation = "enabled";
                }
                $choices[$hourName]["minutes"][$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $j];
            }
        }
        
        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }

        foreach ($bookings as $booking) {
            if ($oldBooking != $booking) {
                foreach ($choices as $choice) {
                    $hourTest = new \DateTime($year."-".$month."-".$day." ".$choice["value"].":0"); 
                    if ($booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H") && $hourTest->format("Y-m-d H") <= $booking->getEndDate()->format("Y-m-d H")) {
                        foreach ($choice["minutes"] as $minute) {
                            $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                            if ($booking->getStartDate()->format("Y-m-d H:i") <= $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") < $booking->getEndDate()->format("Y-m-d H:i")) {
                                $choices[$choice["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                            }
                            else if ($booking->getStartDate()->format("Y-m-d H") == $minuteTest->format("Y-m-d H") && $booking->getStartDate()->format("Y-m-d H:i") > $minuteTest->format("Y-m-d H:i") && $minuteTest->diff($booking->getStartDate())->format("%i") <= 15) {
                                $choices[$choice["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                            }
                        }   
                    }
                }
            }
        }

        foreach ($choices as $choice) {
            $disabledHour = true;
            foreach ($choice["minutes"] as $minute) {
                if ($minute["activation"] == "enabled") {
                    $disabledHour = false;
                }
            }
            if ($disabledHour == false) {
                $choices[$choice["name"]]['activation'] = "enabled";
            }
            else {
                $choices[$choice["name"]]['activation'] = "disabled";
            }
            usort($choices[$choice["name"]]["minutes"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});

        return $this->json($choices);
    }

    
	#[Post('/api/bookings/hourStart', name: 'api_hour_start_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onHourStartChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("today");

        $choices = [];
        $hour = $data['dates']['dateTimeStart']['hour'];
        $day = $data['dates']['dateTimeStart']['day'];
        $month = $data['dates']['dateTimeStart']['month'];
        $year = $data['dates']['dateTimeStart']['year'];

        for ($i = 0; $i <= 45; $i+=15) {
            $minuteName = "";
            if ($i < 10) { $minuteName = "0";}
            $minuteName .= (string)$i;
            $dateTest = new \DateTime($year."-".$month."-".$day." ".$hour.":".$i);
            if ($dateTest < $today) {
                $activation = "disabled";
            }
            else {
                $activation = "enabled";
            }
            $choices[$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $i];
        }

        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }

        foreach ($bookings as $booking) {
            if ($oldBooking != $booking) {
                foreach ($choices as $choice) {
                    $minuteTest = new \DateTime($year."-".$month."-".$day." ".$hour.":".$choice["value"]);
                    if ($booking->getStartDate()->format("Y-m-d H:i") <= $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") < $booking->getEndDate()->format("Y-m-d H:i")) {
                        $choices[$choice["name"]]["activation"] = "disabled";
                    }
                    else if ($booking->getStartDate()->format("Y-m-d H") == $minuteTest->format("Y-m-d H") && $booking->getStartDate()->format("Y-m-d H:i") > $minuteTest->format("Y-m-d H:i") && $minuteTest->diff($booking->getStartDate())->format("%i") <= 15) {
                        $choices[$choice["name"]]["activation"] = "disabled";
                    }
                }
            }
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        
        return $this->json($choices);
    }

    
	#[Post('/api/bookings/yearEnd', name: 'api_year_end_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onYearEndChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("now");
        $yearStart = isset($data['dates']['dateTimeStart']['year']) ? $data['dates']['dateTimeStart']['year'] : (int)$today->format("Y");
        $monthStart = isset($data['dates']['dateTimeStart']['month']) ? $data['dates']['dateTimeStart']['month'] : (int)$today->format("m");
        $dayStart = isset($data['dates']['dateTimeStart']['day']) ? $data['dates']['dateTimeStart']['day'] : (int)$today->format("d");
        $hourStart = isset($data['dates']['dateTimeStart']['hour']) ? $data['dates']['dateTimeStart']['hour'] : 0;
        $minuteStart = isset($data['dates']['dateTimeStart']['minute']) ? $data['dates']['dateTimeStart']['minute'] : 0;
        $dateStart = new \DateTime($yearStart."-".$monthStart."-".$dayStart." ".$hourStart.":".$minuteStart);
        $year = $data['dates']['dateTimeEnd']['year'];
        $choices = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthName = "";
            if ($i < 10) { $monthName = "0";}
            $monthName .= (string)$i;
            $choices[$monthName] = ["name" => $monthName, "activation" => "enabled", "value" => $i];
            if ($i == 4 || $i == 6 || $i == 9 || $i == 11) {
                $days = 30;
            }
            else if ($i == 2) {
                if (($year % 4 == 0 && $year % 100 != 0) || ($year % 4 == 0 && $year % 400 == 0)) {
                    $days = 29;
                }
                else {
                    $days = 28;
                }
            }
            else {
                $days = 31;
            }
            for ($j = 1; $j <= $days; $j++) {
                $dayName = "";
                if ($j < 10) { $dayName = "0";}
                $dayName .= (string)$j;
                $choices[$monthName]["days"][$dayName] = ["name" => $dayName, "activation" => "enabled", "value" => $j];
                for ($k = 0; $k <= 23; $k++) {
                    $hourName = "";
                    if ($k < 10) { $hourName = "0";}
                    $hourName .= (string)$k;
                    $choices[$monthName]["days"][$dayName]["hours"][$hourName] = ["name" => $hourName, "activation" => "enabled", "value" => $k];
                    for ($l = 0; $l <= 45; $l+=15) {
                        $minuteName = "";
                        if ($l < 10) { $minuteName = "0";}
                        $minuteName .= (string)$l;
                        $dateTest = new \DateTime($year."-".$i."-".$j." ".$k.":".$l);
                        $todayPlus15Minutes = new \DateTime("+15 minutes");
                        if ($dateTest < $todayPlus15Minutes) {
                            $activation = "disabled";
                        }
                        else {
                            $activation = "enabled";
                        }
                        $choices[$monthName]["days"][$dayName]["hours"][$hourName]["minutes"][$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $l];
                    }
                }
            }
        }

        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }

        foreach ($bookings as $booking) {
            if ($oldBooking != $booking) {
                foreach ($choices as $choice) {
                    $monthTest = new \DateTime($year."-".$choice["value"]);
                    if ($dateStart->format("Y-m") <= $booking->getStartDate()->format("Y-m") && $booking->getStartDate()->format("Y-m") <= $monthTest->format("Y-m")) {
                        foreach ($choice["days"] as $day) {
                            $dayTest = new \DateTime($monthTest->format("Y-m")."-".$day["value"]); 
                            if ($dateStart->format("Y-m-d") <= $booking->getStartDate()->format("Y-m-d") && $booking->getStartDate()->format("Y-m-d") <= $dayTest->format("Y-m-d")) {
                                foreach ($day["hours"] as $hour) {
                                    $hourTest = new \DateTime($dayTest->format("Y-m-d")." ".$hour["value"].":0");
                                    if ($dateStart->format("Y-m-d H") <= $booking->getStartDate()->format("Y-m-d H") && $booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H")) {
                                        foreach ($hour["minutes"] as $minute) {
                                            $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                                            if ($dateStart->format("Y-m-d H:i") <= $booking->getStartDate()->format("Y-m-d H:i") && $booking->getStartDate()->format("Y-m-d H:i") < $minuteTest->format("Y-m-d H:i")) {
                                                $choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                            }
                                        }
                                    }                                
                                }
                            }
                        }
                    }
                    if ($booking->getStartDate()->format("Y-m") <= $monthTest->format("Y-m") && $monthTest->format("Y-m") <= $booking->getEndDate()->format("Y-m")) {
                        foreach ($choice["days"] as $day) {
                            $dayTest = new \DateTime($monthTest->format("Y-m")."-".$day["value"]); 
                            if ($booking->getStartDate()->format("Y-m-d") <= $dayTest->format("Y-m-d") && $dayTest->format("Y-m-d") <= $booking->getEndDate()->format("Y-m-d")) {
                                foreach ($day["hours"] as $hour) {
                                    $hourTest = new \DateTime($dayTest->format("Y-m-d")." ".$hour["value"].":0");
                                    if ($booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H") && $hourTest->format("Y-m-d H") <= $booking->getEndDate()->format("Y-m-d H")) {
                                        foreach ($hour["minutes"] as $minute) {
                                            $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                                            if ($booking->getStartDate()->format("Y-m-d H:i") < $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") <= $booking->getEndDate()->format("Y-m-d H:i")) {
                                                $choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                            }
                                        }
                                    }                                
                                }
                            }
                        }
                    }
                }
            }  
        }

        foreach ($choices as $choice) {
            $disabledMonth = true;
            foreach ($choice["days"] as $day) {
                $disabledDay = true;
                foreach ($day["hours"] as $hour) {
                    $disabledHour = true;
                    foreach ($hour["minutes"] as $minute) {
                        if ($minute["activation"] == "enabled") {
                            $disabledHour = false;
                        }
                    }
                    if ($disabledHour == false) {
                        $choices[$choice["name"]]["days"][$day["name"]]['hours'][$hour["name"]]['activation'] = "enabled";
                        $hour["activation"] = "enabled";
                    }
                    else {
                        $choices[$choice["name"]]["days"][$day["name"]]['hours'][$hour["name"]]['activation'] = "disabled";
                        $hour["activation"] = "disabled";
                    }
                    if ($hour["activation"] == "enabled") {
                        $disabledDay = false;
                    }
                    usort($choices[$choice["name"]]["days"][$day["name"]]["hours"][$hour["name"]]["minutes"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
                }
                if ($disabledDay == false) {
                    $choices[$choice["name"]]["days"][$day["name"]]['activation'] = "enabled";
                    $day['activation'] = "enabled";
                }
                else {
                    $choices[$choice["name"]]["days"][$day["name"]]['activation'] = "disabled";
                    $day['activation'] = "disabled";
                }
                if ($day["activation"] == "enabled") {
                    $disabledMonth = false;
                }
                usort($choices[$choice["name"]]["days"][$day["name"]]["hours"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
            }
            if ($disabledMonth == false) {
                $choices[$choice["name"]]['activation'] = "enabled";
            }
            else {
                $choices[$choice["name"]]['activation'] = "disabled";
            }
            usort($choices[$choice["name"]]["days"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        
        return $this->json($choices);
    }

    
	#[Post('/api/bookings/monthEnd', name: 'api_month_end_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onMonthEndChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("now");
        $yearStart = isset($data['dates']['dateTimeStart']['year']) ? $data['dates']['dateTimeStart']['year'] : (int)$today->format("Y");
        $monthStart = isset($data['dates']['dateTimeStart']['month']) ? $data['dates']['dateTimeStart']['month'] : (int)$today->format("m");
        $dayStart = isset($data['dates']['dateTimeStart']['day']) ? $data['dates']['dateTimeStart']['day'] : (int)$today->format("d");
        $hourStart = isset($data['dates']['dateTimeStart']['hour']) ? $data['dates']['dateTimeStart']['hour'] : 0;
        $minuteStart = isset($data['dates']['dateTimeStart']['minute']) ? $data['dates']['dateTimeStart']['minute'] : 0;
        $dateStart = new \DateTime($yearStart."-".$monthStart."-".$dayStart."-".$hourStart."-".$minuteStart);
        $month = $data['dates']['dateTimeEnd']['month'];
        $year = $data['dates']['dateTimeEnd']['year'];
        $dateStart = new \DateTime($yearStart."-".$monthStart."-".$dayStart." ".$hourStart.":".$minuteStart);

        if ($month == 4 || $month == 6 || $month == 9 || $month == 11) {
            $days = 30;
        }
        else if ($month == 2) {
            if (($year % 4 == 0 && $year % 100 != 0) || ($year % 4 == 0 && $year % 400 == 0)) {
                $days = 29;
            }
            else {
                $days = 28;
            }
        }
        else {
            $days = 31;
        }

        for ($i = 1; $i <= $days; $i++) {
            $dayName = "";
            if ($i < 10) { $dayName = "0";}
            $dayName .= (string)$i;
            $choices[$dayName] = ["name" => $dayName, "activation" => "enabled", "value" => $i];
            for ($j = 0; $j <= 23; $j++) {
                $hourName = "";
                if ($j < 10) { $hourName = "0";}
                $hourName .= (string)$j;
                $choices[$dayName]["hours"][$hourName] = ["name" => $hourName, "activation" => "enabled", "value" => $j];
                for ($k = 0; $k <= 45; $k+=15) {
                    $minuteName = "";
                    if ($k < 10) { $minuteName = "0";}
                    $minuteName .= (string)$k;
                    $dateTest = new \DateTime($year."-".$month."-".$i." ".$j.":".$k);
                    $todayPlus15Minutes = new \DateTime("+15 minutes");
                    if ($dateTest < $todayPlus15Minutes) {
                        $activation = "disabled";
                    }
                    else {
                        $activation = "enabled";
                    }
                    $choices[$dayName]["hours"][$hourName]["minutes"][$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $k];
                }
            }
        }

        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }

        foreach ($bookings as $booking) {
            if ($oldBooking != $booking) {
                foreach ($choices as $choice) {
                    $dayTest = new \DateTime($year."-".$month."-".$choice["value"]); 
                    if ($dateStart->format("Y-m-d") <= $booking->getStartDate()->format("Y-m-d") && $booking->getStartDate()->format("Y-m-d") <= $dayTest->format("Y-m-d")) {
                        foreach ($choice["hours"] as $hour) {
                            $hourTest = new \DateTime($dayTest->format("Y-m-d")." ".$hour["value"].":0");
                            if ($dateStart->format("Y-m-d H") <= $booking->getStartDate()->format("Y-m-d H") && $booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H")) {
                                foreach ($hour["minutes"] as $minute) {
                                    $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                                    if ($dateStart->format("Y-m-d H:i") <= $booking->getStartDate()->format("Y-m-d H:i") && $booking->getStartDate()->format("Y-m-d H:i") < $minuteTest->format("Y-m-d H:i")) {
                                        $choices[$choice["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                    }
                                }
                            }                        
                        }
                    }
                    if ($booking->getStartDate()->format("Y-m-d") <= $dayTest->format("Y-m-d") && $dayTest->format("Y-m-d") <= $booking->getEndDate()->format("Y-m-d")) {
                        foreach ($choice["hours"] as $hour) {
                            $hourTest = new \DateTime($dayTest->format("Y-m-d")." ".$hour["value"].":0");
                            if ($booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H") && $hourTest->format("Y-m-d H") <= $booking->getEndDate()->format("Y-m-d H")) {
                                foreach ($hour["minutes"] as $minute) {
                                    $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                                    if ($booking->getStartDate()->format("Y-m-d H:i") < $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") <= $booking->getEndDate()->format("Y-m-d H:i")) {
                                        $choices[$choice["name"]]["hours"][$hour["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                                    }
                                }
                            }                        
                        }
                    }
                }
            }
        }

        foreach ($choices as $choice) {
            $disabledDay = true;
            foreach ($choice["hours"] as $hour) {
                $disabledHour = true;
                foreach ($hour["minutes"] as $minute) {
                    if ($minute["activation"] == "enabled") {
                        $disabledHour = false;
                    }
                }
                if ($disabledHour == false) {
                    $choices[$choice["name"]]['hours'][$hour["name"]]['activation'] = "enabled";
                    $hour["activation"] = "enabled";
                }
                else {
                    $choices[$choice["name"]]['hours'][$hour["name"]]['activation'] = "disabled";
                    $hour["activation"] = "disabled";
                }
                if ($hour["activation"] == "enabled") {
                    $disabledDay = false;
                }
                usort($choices[$choice["name"]]["hours"][$hour["name"]]["minutes"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
            }
            if ($disabledDay == false) {
                $choices[$choice["name"]]['activation'] = "enabled";
            }
            else {
                $choices[$choice["name"]]['activation'] = "disabled";
            }
            usort($choices[$choice["name"]]["hours"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        return $this->json($choices);
    }

    
	#[Post('/api/bookings/dayEnd', name: 'api_day_end_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onDayEndChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("now");
        $choices = [];

        $yearStart = isset($data['dates']['dateTimeStart']['year']) ? $data['dates']['dateTimeStart']['year'] : (int)$today->format("Y");
        $monthStart = isset($data['dates']['dateTimeStart']['month']) ? $data['dates']['dateTimeStart']['month'] : (int)$today->format("m");
        $dayStart = isset($data['dates']['dateTimeStart']['day']) ? $data['dates']['dateTimeStart']['day'] : (int)$today->format("d");
        $hourStart = isset($data['dates']['dateTimeStart']['hour']) ? $data['dates']['dateTimeStart']['hour'] : 0;
        $minuteStart = isset($data['dates']['dateTimeStart']['minute']) ? $data['dates']['dateTimeStart']['minute'] : 0;
        $day = $data['dates']['dateTimeEnd']['day'];
        $month = $data['dates']['dateTimeEnd']['month'];
        $year = $data['dates']['dateTimeEnd']['year'];
        $dateStart = new \DateTime($yearStart."-".$monthStart."-".$dayStart." ".$hourStart.":".$minuteStart);

        for ($i = 0; $i <= 23; $i++) {
            $hourName = "";
            if ($i < 10) { $hourName = "0";}
            $hourName .= (string)$i;
            $choices[$hourName] = ["name" => $hourName, "activation" => "enabled", "value" => $i];
            for ($j = 0; $j <= 45; $j+=15) {
                $minuteName = "";
                if ($j < 10) { $minuteName = "0";}
                $minuteName .= (string)$j;
                $dateTest = new \DateTime($year."-".$month."-".$day." ".$i.":".$j);
                $todayPlus15Minutes = new \DateTime("+15 minutes");
                if ($dateTest < $todayPlus15Minutes) {
                    $activation = "disabled";
                }
                else {
                    $activation = "enabled";
                }
                $choices[$hourName]["minutes"][$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $j];
            }
        }
        
        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }

        foreach ($bookings as $booking) {
            if ($oldBooking != $booking) {
                foreach ($choices as $choice) {
                    $hourTest = new \DateTime($year."-".$month."-".$day." ".$choice["value"].":0"); 
                    if ($dateStart->format("Y-m-d H") <= $booking->getStartDate()->format("Y-m-d H") && $booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H")) {
                        foreach ($choice["minutes"] as $minute) {
                            $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                            if ($dateStart->format("Y-m-d H:i") <= $booking->getStartDate()->format("Y-m-d H:i") && $booking->getStartDate()->format("Y-m-d H:i") < $minuteTest->format("Y-m-d H:i")) {
                                $choices[$choice["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                            }
                        }   
                    }
                    if ($booking->getStartDate()->format("Y-m-d H") <= $hourTest->format("Y-m-d H") && $hourTest->format("Y-m-d H") <= $booking->getEndDate()->format("Y-m-d H")) {
                        foreach ($choice["minutes"] as $minute) {
                            $minuteTest = new \DateTime($hourTest->format("Y-m-d H").":".$minute["value"]);
                            if ($booking->getStartDate()->format("Y-m-d H:i") < $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") <= $booking->getEndDate()->format("Y-m-d H:i")) {
                                $choices[$choice["name"]]["minutes"][$minute["name"]]["activation"] = "disabled";
                            }
                        }   
                    }
                }
            }
        }

        foreach ($choices as $choice) {
            $disabledHour = true;
            foreach ($choice["minutes"] as $minute) {
                if ($minute["activation"] == "enabled") {
                    $disabledHour = false;
                }
            }
            if ($disabledHour == false) {
                $choices[$choice["name"]]['activation'] = "enabled";
            }
            else {
                $choices[$choice["name"]]['activation'] = "disabled";
            }
            usort($choices[$choice["name"]]["minutes"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});

        return $this->json($choices);
    }

    
	#[Post('/api/bookings/hourEnd', name: 'api_hour_end_change')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function onHourEndChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("today");

        $choices = [];
        $yearStart = isset($data['dates']['dateTimeStart']['year']) ? $data['dates']['dateTimeStart']['year'] : (int)$today->format("Y");
        $monthStart = isset($data['dates']['dateTimeStart']['month']) ? $data['dates']['dateTimeStart']['month'] : (int)$today->format("m");
        $dayStart = isset($data['dates']['dateTimeStart']['day']) ? $data['dates']['dateTimeStart']['day'] : (int)$today->format("d");
        $hourStart = isset($data['dates']['dateTimeStart']['hour']) ? $data['dates']['dateTimeStart']['hour'] : 0;
        $minuteStart = isset($data['dates']['dateTimeStart']['minute']) ? $data['dates']['dateTimeStart']['minute'] : 0;
        $hour = $data['dates']['dateTimeEnd']['hour'];
        $day = $data['dates']['dateTimeEnd']['day'];
        $month = $data['dates']['dateTimeEnd']['month'];
        $year = $data['dates']['dateTimeEnd']['year'];
        $dateStart = new \DateTime($yearStart."-".$monthStart."-".$dayStart." ".$hourStart.":".$minuteStart);

        for ($i = 0; $i <= 45; $i+=15) {
            $minuteName = "";
            if ($i < 10) { $minuteName = "0";}
            $minuteName .= (string)$i;
            $dateTest = new \DateTime($year."-".$month."-".$day." ".$hour.":".$i);
            $todayPlus15Minutes = new \DateTime("+15 minutes");
            if ($dateTest < $todayPlus15Minutes) {
                $activation = "disabled";
            }
            else {
                $activation = "enabled";
            }
            $choices[$minuteName] = ["name" => $minuteName, "activation" => $activation, "value" => $i];
        }

        $lab = $this->labRepository->find($data['labId']);
        $bookings = $this->bookingRepository->findBy(["lab" => $lab]);
        if (isset($data["bookingId"])) {
            $oldBooking = $this->bookingRepository->findOneBy(["id" => $data["bookingId"]]);
        }

        foreach ($bookings as $booking) {
            if ($oldBooking != $booking) {
                foreach ($choices as $choice) {
                    $minuteTest = new \DateTime($year."-".$month."-".$day." ".$hour.":".$choice["value"]);
                    if ($booking->getStartDate()->format("Y-m-d H:i") < $minuteTest->format("Y-m-d H:i") && $minuteTest->format("Y-m-d H:i") <= $booking->getEndDate()->format("Y-m-d H:i")) {
                        $choices[$choice["name"]]["activation"] = "disabled";
                    }
                }
            }
        }
        usort($choices, function ($a,$b) {return strcmp($a["name"], $b["name"]);});
        
        return $this->json($choices);
    }
}
