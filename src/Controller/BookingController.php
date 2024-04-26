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

    /**
     * @Route("/bookings/lab/{id<\d+>}/new", name="new_booking", methods={"GET", "POST"})
     * 
     * @Rest\Post("/api/bookings/lab/{id<\d+>}", name="api_new_booking")
     */
    public function newAction(Request $request, int $id)
    {
        $booking = new Booking();
        /*$bookingForm = $this->createForm(BookingType::class, $booking);
        $bookingForm->handleRequest($request);*/

        $lab = $this->labRepository->find($id);
        if ($lab->getVirtuality() == 1) {

        }
        if ($request->getContentType() === 'json') {
            $booking = json_decode($request->getContent(), true);
            $bookingForm->submit($booking);
        }

        return $this->render('booking/new.html.twig', [
            //'bookingForm' => $bookingForm->createView(),
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
}
