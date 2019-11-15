<?php

namespace App\Controller;

use IPTools;
use App\Entity\Network;

use App\Entity\Activity;
use App\Entity\Instance;
use App\Form\ActivityType;
use App\Service\FileUploader;
use App\Repository\ActivityRepository;
use Symfony\Component\Process\Process;
use App\Repository\LabInstanceRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\UserInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;


class ActivityController extends AppController
{
    private $activityRepository;

    public function __construct(ActivityRepository $activityRepository, LabInstanceRepository $labInstanceRepository)
    {
        $this->activityRepository = $activityRepository;
        $this->labInstanceRepository = $labInstanceRepository;
    }

    /**
     * @Route("/activities", name="activities")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
    
        if ($search !== '') {
            $data = $this->activityRepository->findByNameLike($search);
        } else {
            $data = $this->activityRepository->findAll();
        }
        
        return $this->render('activity/index.html.twig', [
            'activities' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/activities/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_activity",
     *  methods="GET")
     */
    public function showAction(Request $request, $id, UserInterface $user)
    {
        $data = $this->activityRepository->find($id);
        $labInstance = null;

        if ($data->getLab()) {
            $labInstance_tmp = $this->labInstanceRepository->findByUserAndLab($user, $data->getLab());
            if (count($labInstance_tmp) > 0)
                $labInstance=$labInstance_tmp[0];
        }

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->renderJson($data);
        }
        
        return $this->render('activity/view.html.twig', [
            'activity' => $data,
            'labInstance' => $labInstance
        ]);
    }

    /**
     * @Route("/activities/new", name="new_activity")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $activity = new Activity();
        $activityForm = $this->createForm(ActivityType::class, $activity);
        $activityForm->handleRequest($request);
        
        if ($activityForm->isSubmitted() && $activityForm->isValid()) {
            $activity = $activityForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($activity);
            $entityManager->flush();
            
            $this->addFlash('success', 'Activity has been created.');

            return $this->redirectToRoute('activities');
        }
        
        return $this->render('activity/new.html.twig', [
            'activityForm' => $activityForm->createView(),
        ]);
    }

    /**
     * @Route("/activities/{id<\d+>}/edit", name="edit_activity", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMINISTRATOR")
     */
    public function editAction(Request $request, $id)
    {
        $activity = $this->activityRepository->find($id);

        if (null === $activity) {
            throw new NotFoundHttpException();
        }

        $activityForm = $this->createForm(ActivityType::class, $activity);
        $activityForm->handleRequest($request);
        
        if ($activityForm->isSubmitted() && $activityForm->isValid()) {
            $activity = $activityForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($activity);
            $entityManager->flush();
            
            $this->addFlash('success', 'Activity has been edited.');

            return $this->redirectToRoute('show_activity', [
                'id' => $id
            ]);
        }
        
        return $this->render('activity/new.html.twig', [
            'activityForm' => $activityForm->createView(),
            'id' => $id,
            'name' => $activity->getName()
        ]);
    }

    /**
     * @Route("/activities/{id<\d+>}/delete", name="delete_activity", methods="GET")
     * @IsGranted("ROLE_ADMINISTRATOR")
     */
    public function deleteAction(int $id)
    {
        $activity = $this->activityRepository->find($id);

        if (null === $activity) {
            throw new NotFoundHttpException();
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($activity);
        $entityManager->flush();

        $this->addFlash('success', $activity->getName() . ' has been deleted.');

        return $this->redirectToRoute('activities');
    }

    /**
     * @Route("/activities/{id<\d+>}/start", name="start_activity", methods="GET")
     */
    public function startActivityAction(int $id)
    {
        $lab = $this->activityRepository->find($id)->getLab();

        return $this->redirectToRoute('start_lab_activity', [
            'id' => $lab->getId(),
            'activity_id' => $id
        ]);

    }
}
