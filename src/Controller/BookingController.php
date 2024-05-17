<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use App\Repository\LabRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class BookingController extends Controller
{
    public $labRepository;
    public $bookingRepository;

    public function __construct(LabRepository $labRepository, BookingRepository $bookingRepository)
    {
        $this->labRepository = $labRepository;
        $this->bookingRepository = $bookingRepository;
    }

    /**
     * @Route("/bookings", name="bookings")
     * 
     * @Rest\Get("/api/bookings", name="api_bookings")
     * 
     */
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

    /**
     *  @Route("/bookings/lab/{id<\d+>}", name="show_lab_bookings")
     * @Rest\Get("/api/bookings/lab/{id<\d+>}", name="api_show_lab_bookings")
     * 
     */
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

    public function fetchOldBookingInstances(Request $request)
    {
        $bookings = $this->bookingReppository->findBy(new \DateTime());


    }
    /**
     * @Route("/bookings/lab/{id<\d+>}/new", name="new_booking", methods={"GET", "POST"})
     * 
     * @Rest\Post("/api/bookings/lab/{id<\d+>}", name="api_new_booking")
     */
    public function newAction(Request $request, int $id)
    {
        $booking = new Booking();
        $bookingForm = $this->createForm(BookingType::class, $booking);
        $bookingForm->handleRequest($request);

        $lab = $this->labRepository->find($id);
        if ($lab->getVirtuality() == 0) {
            if ($bookingForm->isSubmitted() && $bookingForm->isValid()) {
                $booking = $bookingForm->getData();
                $booking->setAuthor($this->getUser());
                $booking->setLab($lab);
                $booking->setStartDate(new \DateTime($bookingForm["dayStart"]->getData()."-".$bookingForm['monthStart']->getData()."-".$bookingForm['yearStart']->getData()." ".$bookingForm["hourStart"]->getData().":".$bookingForm["minuteStart"]->getData()));
                $booking->setEndDate(new \DateTime($bookingForm["dayEnd"]->getData()."-".$bookingForm['monthEnd']->getData()."-".$bookingForm['yearEnd']->getData()." ".$bookingForm["hourEnd"]->getData().":".$bookingForm["minuteEnd"]->getData()));
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($booking);
                $entityManager->flush();

                if ('json' === $request->getRequestFormat()) {
                    return $this->json($booking, 201, [], ['api_get_booking']);
                }
                return $this->redirectToRoute('show_lab_bookings', ['id' => $lab->getId()]);
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

    /**
     * @Route("/admin/bookings/{id<\d+>}/edit", name="edit_booking")
     * 
     * @Rest\Put("/api/bookings/{id<\d+>}", name="api_edit_booking")
     * 
     */
    public function editAction(Request $request, int $id)
    {

    }

    /**
     * @Route("/admin/bookings/{id<\d+>}/delete", name="delete_booking", methods="GET")
     * 
     * @Rest\Delete("/api/bookings/{id<\d+>}", name="api_delete_booking")
     * 
     */
    public function deleteAction(Request $request, int $id)
    {
        if (!$booking = $this->bookingRepository->find($id)) {
            throw new NotFoundHttpException("Booking " . $id . " does not exist.");
        }
        $lab = $booking->getLab();

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($booking);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('show_lab_bookings', ['id' => $lab->getId()]);
    }

    /** 
     * @Rest\Post("/api/bookings/yearStart", name="api_year_start_change")
     * 
     */
    public function onYearStartChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        if ($data['dates']['dateTimeStart']['year'] < date('Y')) {
            //error
        }
        $lab = $this->labRepository->find($data['labId']);
        if (!$bookings = $this->bookingRepository->findBy(["lab" => $lab])) {
            return $this->json($data);
            //check if dateEnd > dateStart
        }
        
        return $this->json($lab->getId());
    }

    /** 
     * @Rest\Post("/api/bookings/dayStart", name="api_day_start_change")
     * 
     */
    public function onDayStartChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("now");
        $minuteStart = $data['dates']['dateTimeStart']['minute'] ? $data['dates']['dateTimeStart']['minute'] : 0;
        $minuteEnd = $data['dates']['dateTimeEnd']['minute'] ? $data['dates']['dateTimeEnd']['minute'] : 0;
        $hourStart = $data['dates']['dateTimeStart']['hour'] ? $data['dates']['dateTimeStart']['hour'] : 0;
        $hourEnd = $data['dates']['dateTimeEnd']['hour'] ? $data['dates']['dateTimeEnd']['hour'] : 0;
        $minuteChoices = [["name"=>"00","activation"=>"enabled", "value"=>0], ["name"=>"15","activation"=>"enabled", "value"=>15], ["name"=>"30","activation"=>"enabled", "value"=>30], ["name"=>"45","activation"=>"enabled", "value"=>45]];
        $hourChoices = [
            ["name"=>"00","activation"=>"enabled", "value"=>0], 
            ["name"=>"01","activation"=>"enabled", "value"=>1], 
            ["name"=>"02","activation"=>"enabled", "value"=>2], 
            ["name"=>"03","activation"=>"enabled", "value"=>3], 
            ["name"=>"04","activation"=>"enabled", "value"=>4],
            ["name"=>"05","activation"=>"enabled", "value"=>5], 
            ["name"=>"06","activation"=>"enabled", "value"=>6], 
            ["name"=>"07","activation"=>"enabled", "value"=>7], 
            ["name"=>"08","activation"=>"enabled", "value"=>8],
            ["name"=>"09","activation"=>"enabled", "value"=>9], 
            ["name"=>"10","activation"=>"enabled", "value"=>10], 
            ["name"=>"11","activation"=>"enabled", "value"=>11], 
            ["name"=>"12","activation"=>"enabled", "value"=>12],
            ["name"=>"13","activation"=>"enabled", "value"=>13], 
            ["name"=>"14","activation"=>"enabled", "value"=>14],
            ["name"=>"15","activation"=>"enabled", "value"=>15], 
            ["name"=>"16","activation"=>"enabled", "value"=>16], 
            ["name"=>"17","activation"=>"enabled", "value"=>17], 
            ["name"=>"18","activation"=>"enabled", "value"=>18],
            ["name"=>"19","activation"=>"enabled", "value"=>19], 
            ["name"=>"20","activation"=>"enabled", "value"=>20],
            ["name"=>"21","activation"=>"enabled", "value"=>21], 
            ["name"=>"22","activation"=>"enabled", "value"=>22], 
            ["name"=>"23","activation"=>"enabled", "value"=>23], 
        ];
        $disabledChoices = [];
        $dateStart = new \DateTime($data['dates']['dateTimeStart']['year']."-".$data['dates']['dateTimeStart']['month']."-".$data['dates']['dateTimeStart']['day']);
        $dateTimeStart = new \DateTime($data['dates']['dateTimeStart']['year']."-".$data['dates']['dateTimeStart']['month']."-".$data['dates']['dateTimeStart']['day']." ".$hourStart.":".$minuteStart);
        $dateTimeEnd = new \DateTime($data['dates']['dateTimeEnd']['year']."-".$data['dates']['dateTimeEnd']['month']."-".$data['dates']['dateTimeEnd']['day']." ".$hourStart.":".$minuteEnd);
        if ($dateStart->format("Y-m-d") < $today->format("Y-m-d")) {
            foreach ($hourChoices as $key => $hourChoice) {
                $disabledChoices[$hourChoice["name"]] = ["activation" => "disabled", "name" => $hourChoice["name"], "value" => $hourChoice["value"]]; 

                foreach ($minuteChoices as $minuteChoice) {
                    $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                }              
            }
        }
        else if ($dateStart->format("Y-m-d") == $today->format("Y-m-d")) {
            foreach ($hourChoices as $key => $hourChoice) {
                $dateTest = new \DateTime($dateStart->format("Y-m-d")." ".$hourChoice['value'].":0");
                if ($dateTest->format("H") < $today->format("H")) {
                    $disabledChoices[$hourChoice["name"]] = ["activation" => "disabled", "name" => $hourChoice["name"], "value" => $hourChoice["value"]]; 
    
                    foreach ($minuteChoices as $minuteChoice) {
                        $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                    }              
                }
                else if ($dateTest->format("H") == $today->format("H")) {
                    foreach ($minuteChoices as $key => $minuteChoice) {
                        $minuteTest = new \DateTime($dateTest->format("Y-m-d H").":".$minuteChoice['value']);
                        if ($minuteTest->format("i") <= $today->format("i")) {
                            if (array_key_exists($today->format("H"), $disabledChoices)) {
                                $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                            }
                            else {
                                $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                            }
                        }
                    }
                }

            }
        }
        
        $lab = $this->labRepository->find($data['labId']);
        if (!$bookings = $this->bookingRepository->findBy(["lab" => $lab])) {
            return $this->json(["choices"=> ["minute"=> $minuteChoices, "hours" => $hourChoices]]);
            //check if dateEnd > dateStart
        }

        foreach ($bookings as $booking) {
            if ($booking->getStartDate()->format("Y-m-d") <= $dateStart->format("Y-m-d") && $dateStart->format("Y-m-d") <= $booking->getEndDate()->format("Y-m-d")) {
                if ($booking->getStartDate()->format("Y-m-d") == $dateStart->format("Y-m-d") && $dateStart->format("Y-m-d") < $booking->getEndDate()->format("Y-m-d")) {
                    foreach ($hourChoices as $hourChoice) {
                        $dateTest = new \DateTime($dateStart->format("Y-m-d")." ".$hourChoice['value'].":0");
                        if ($booking->getStartDate()->format('H') < $dateTest->format('H')) {
                            $disabledChoices[$hourChoice['name']] = ["activation" => "disabled", "name" => $hourChoice['name'], "value" => $hourChoice['value']];
                            foreach ($minuteChoices as $minuteChoice) {
                                $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                            }
                        }
                        else if ($booking->getStartDate()->format('H') == $dateTest->format('H')){
                            foreach ($minuteChoices as $minuteChoice) {
                                if ($booking->getStartDate()->format('i') <= $minuteChoice['value']) {
                                    if (array_key_exists($booking->getStartDate()->format('H'), $disabledChoices)) {
                                        $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                    }
                                    else {
                                        $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                    }
                                }
                                else {
                                    $minuteTest = new \DateTime($dateTest->format("Y-m-d H").":".$minuteChoice['value']);
                                    if ($minuteTest->diff($booking->getStartDate())->format("%i") < 15) {
                                        if (array_key_exists($booking->getStartDate()->format('H'), $disabledChoices)) {
                                            $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                        }
                                        else {
                                            $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                        }
                                    }
                                }
                            }
                        }
                        else {
                            if ($dateTest->diff($booking->getStartDate())->format("%H") == 1) {
                                foreach ($minuteChoices as $minuteChoice) {
                                    $minuteTest = new \DateTime($dateTest->format("Y-m-d H").":".$minuteChoice['value']);
                                    if ($minuteTest->diff($booking->getStartDate())->format("%i") < 15) {
                                        if (array_key_exists($booking->getStartDate()->format('H'), $disabledChoices)) {
                                            $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                        }
                                        else {
                                            $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                        }
                                    }
                                }
                                
                            }
                        }
                    }
                }
                else if ($booking->getStartDate()->format("Y-m-d") == $dateStart->format("Y-m-d") && $dateStart->format("Y-m-d") == $booking->getEndDate()->format("Y-m-d")) {
                    foreach ($hourChoices as $hourChoice) {
                        $dateTest = new \DateTime($dateStart->format("Y-m-d")." ".$hourChoice['value'].":0");
                        if ($booking->getStartDate()->format('H') < $dateTest->format('H') && $dateTest->format('H') < $booking->getEndDate()->format('H')) {
                            $disabledChoices[$hourChoice['name']] = ["activation" => "disabled", "name" => $hourChoice['name'], "value" => $hourChoice['value']];
                            foreach ($minuteChoices as $minuteChoice) {
                                $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                            }
                        }
                        else if ($booking->getStartDate()->format('H') < $dateTest->format('H') && $dateTest->format('H') == $booking->getEndDate()->format('H')) {
                            foreach ($minuteChoices as $minuteChoice) {
                                $minuteTest = new \DateTime($dateTest->format("Y-m-d H").":".$minuteChoice['value']);
                                if ($minuteTest->format("i") < $booking->getEndDate()->format("i")) {
                                    if (array_key_exists($booking->getEndDate()->format('H'), $disabledChoices)) {
                                        $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                    }
                                    else {
                                        $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                    }
                                }
                            }
                        }
                        else if ($booking->getStartDate()->format('H') == $dateTest->format('H') && $dateTest->format('H') < $booking->getEndDate()->format('H')) {
                            foreach ($minuteChoices as $minuteChoice) {
                                $minuteTest = new \DateTime($dateTest->format("Y-m-d H").":".$minuteChoice['value']);
                                if ($booking->getStartDate()->format("i") <= $minuteTest->format("i")) {
                                    if (array_key_exists($booking->getStartDate()->format('H'), $disabledChoices)) {
                                        $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                    }
                                    else {
                                        $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                    }
                                }
                                else {
                                    if ($minuteTest->diff($booking->getStartDate())->format("%i") < 15) {
                                        if (array_key_exists($booking->getStartDate()->format('H'), $disabledChoices)) {
                                            $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                        }
                                        else {
                                            $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                        }
                                    }
                                }
                            }
                        }
                        else if ($booking->getStartDate()->format('H') == $dateTest->format('H') && $dateTest->format('H') == $booking->getEndDate()->format('H')) {
                            foreach ($minuteChoices as $minuteChoice) {
                                $minuteTest = new \DateTime($dateTest->format("Y-m-d H").":".$minuteChoice['value']);
                                if ($minuteTest->format("i") < $booking->setStartDate()->format("i") && $minuteTest->diff($booking->getStartDate())->format("%i") < 15) {
                                    if (array_key_exists($booking->getStartDate()->format('H'), $disabledChoices)) {
                                        $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                    }
                                    else {
                                        $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                    }
                                }
                                else if ($booking->getStartDate()->format("i") <= $minuteTest->format("i") && $minuteTest->format("i") < $booking->getEndDate()->format('i')) {
                                    if (array_key_exists($booking->getStartDate()->format('H'), $disabledChoices)) {
                                        $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                    }
                                    else {
                                        $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                    }
                                }
                            }
                        }
                    }
                }
                else if ($booking->getStartDate()->format("Y-m-d") < $dateStart->format("Y-m-d") && $dateStart->format("Y-m-d") == $booking->getEndDate()->format("Y-m-d")) {
                    foreach ($hourChoices as $hourChoice) {
                        $dateTest = new \DateTime($dateStart->format("Y-m-d")." ".$hourChoice['value'].":0");
                        if ($dateTest->format('H') < $booking->getEndDate()->format('H')) {
                            $disabledChoices[$hourChoice['name']] = ["activation" => "disabled", "name" => $hourChoice['name'], "value" => $hourChoice['value']];
                            foreach ($minuteChoices as $minuteChoice) {
                                $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                            }
                        }
                        else if ($dateTest->format('H') == $booking->getEndDate()->format('H')) {
                            foreach ($minuteChoices as $minuteChoice) {
                                $minuteTest = new \DateTime($dateTest->format("Y-m-d H").":".$minuteChoice['value']);
                                if ($minuteTest->format("i") <= $booking->getEndDate()->format("i")) {
                                    if (array_key_exists($booking->getEndDate()->format('H'), $disabledChoices)) {
                                        $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                                    }
                                    else {
                                        $disabledChoices[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value'], "minutes" => [$minuteChoice['name'] => ["activation" => "disabled", "name" => $minuteChoice["name"], "value" => $minuteChoice['value']]]];
                                    }
                                }
                            }
                        }
                    }
                }
                else {
                    foreach ($hourChoices as $hourChoice) {
                        $disabledChoices[$hourChoice['name']] = ["activation" => "disabled", "name" => $hourChoice['name'], "value" => $hourChoice['value']];
                        foreach ($minuteChoices as $minuteChoice) {
                            $disabledChoices[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "disabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                        }
                    }
                }
            }

            //check if an existing booking is between the start and the end
            if ($dateTimeStart <= $booking->getStartDate() && $booking->getStartDate() < $dateTimeEnd) {
                //do something
            }

            $choiceList = $disabledChoices;
            foreach ($hourChoices as $hourChoice) {
                if (!array_key_exists($hourChoice["name"], $choiceList)) {
                    $choiceList[$hourChoice["name"]] = ["activation" => "enabled", "name" => $hourChoice['name'], "value" => $hourChoice['value']];
                    foreach ($minuteChoices as $minuteChoice) {
                        $choiceList[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "enabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                    }
                }
                else {
                    foreach ($minuteChoices as $minuteChoice) {
                        if (!array_key_exists($minuteChoice["name"], $choiceList[$hourChoice["name"]]["minutes"])) {
                            $choiceList[$hourChoice["name"]]["minutes"][$minuteChoice["name"]] = ["activation" => "enabled", "name" => $minuteChoice['name'], "value" => $minuteChoice["value"]]; 
                        }
                    }
                }
            }
            foreach ($choiceList as $key => $choice) {
                $disabledHour = true;
                foreach ($choice["minutes"] as $minute) {
                    if ($minute['activation'] == "enabled") {
                        $disabledHour = false; break;
                    }
                }
                if ($disabledHour == false) {
                    $choiceList[$key]['activation'] = "enabled";
                }
                else {
                    $choiceList[$key]['activation'] = "disabled";
                }
                usort($choiceList[$key]["minutes"], function ($a,$b) {return strcmp($a["name"], $b["name"]);});
            }
            
        }
        
        usort($choiceList, function ($a,$b) {return strcmp($a["name"], $b["name"]);});

        $choices["minuteChoices"] = $minuteChoices;
        $choices["hourChoices"] = $hourChoices;
        return $this->json($choiceList);
    }

    /** 
     * @Rest\Post("/api/bookings/hourStart", name="api_hour_start_change")
     * 
     */
    public function onHourStartChange(Request $request)
    {
        $data = json_decode($request->getContent(), true); 
        $today = new \DateTime("today");
        $minuteStart = $data['dates']['dateTimeStart']['minute'] ? $data['dates']['dateTimeStart']['minute'] : 0;
        $minuteEnd = $data['dates']['dateTimeEnd']['minute'] ? $data['dates']['dateTimeEnd']['minute'] : 0;
        $minuteChoices = [["name"=>"00","activation"=>"enabled", "value"=>0], ["name"=>"15","activation"=>"enabled", "value"=>15], ["name"=>"30","activation"=>"enabled", "value"=>30], ["name"=>"45","activation"=>"enabled", "value"=>45]];
        $dateStart = new \DateTime($data['dates']['dateTimeStart']['year']."-".$data['dates']['dateTimeStart']['month']."-".$data['dates']['dateTimeStart']['day']);
        $dateTimeStart = new \DateTime($data['dates']['dateTimeStart']['year']."-".$data['dates']['dateTimeStart']['month']."-".$data['dates']['dateTimeStart']['day']." ".$data['dates']['dateTimeStart']['hour'].":".$minuteStart);
        $dateTimeEnd = new \DateTime($data['dates']['dateTimeEnd']['year']."-".$data['dates']['dateTimeEnd']['month']."-".$data['dates']['dateTimeEnd']['day']." ".$data['dates']['dateTimeEnd']['hour'].":".$minuteEnd);
        if ( $dateStart < $today) {
            /*foreach ($hourChoices as $key => $hourChoice) {
                $hourChoices[$key]["activation"] = "disabled"; 
            }*/
            foreach ($minuteChoices as $key => $minuteChoice) {
                $minuteChoices[$key]["activation"] = "disabled"; 
            }
        }
        else if ($dateStart == $today) {
            if ($data['dates']['dateTimeStart']['hour'] < date("H")) {
                foreach ($minuteChoices as $key => $minuteChoice) {
                    $minuteChoices[$key]["activation"] = "disabled"; 
                }
            }
            else if ($data['dates']['dateTimeStart']['hour'] == date("H")) {
                foreach ($minuteChoices as $key => $minuteChoice) {
                    if ($minuteChoice["value"] <= date("i")) {
                        $minuteChoices[$key]["activation"] = "disabled"; 
                    }
                }
            }
        }
        $lab = $this->labRepository->find($data['labId']);
        if (!$bookings = $this->bookingRepository->findBy(["lab" => $lab])) {
            return $this->json(["choices"=> ["minute"=> $minuteChoices]]);
            //TODO: check if dateEnd > dateStart
        }
        foreach ($bookings as $booking) {
            //check if the time start during an existing booking
            foreach ($minuteChoices as $key => $minuteChoice) {
                $dateTest = new \DateTime($dateStart->format("Y-m-d")." ".$data['dates']['dateTimeStart']['hour'].":".$minuteChoice["value"]);
                if ($booking->getStartDate() <= $dateTest && $dateTest < $booking->getEndDate()) {
                    $minuteChoices[$key]["activation"] = "disabled";
                }
            }

            //check if an existing booking is between the start and the end
            if ($dateTimeStart <= $booking->getStartDate() && $booking->getStartDate() < $dateTimeEnd) {
                //TODO: do something
            }

        }
        
        return $this->json($minuteChoices);
    }
}
