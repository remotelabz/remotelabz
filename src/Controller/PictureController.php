<?php

namespace App\Controller;

use IPTools;
use App\Entity\Picture;
use App\Entity\Lab;
use GuzzleHttp\Psr7;
use App\Form\LabType;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use App\Repository\PictureRepository;
use App\Repository\LabRepository;
use App\Exception\WorkerException;
use App\Repository\UserRepository;
use FOS\RestBundle\Context\Context;
use Remotelabz\Message\Message\InstanceActionMessage;
use JMS\Serializer\SerializerInterface;
use App\Exception\NotInstancedException;
use JMS\Serializer\SerializationContext;
use Doctrine\Common\Collections\Criteria;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Lab\LabImporter;
use App\Service\LabBannerFileUploader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


class PictureController extends Controller
{
    private $workerServer;

    private $workerPort;

    private $workerAddress;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var PictureRepository $textobjectRepository */
    private $picturetRepository;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializerInterface,
        LabRepository $labRepository,
        PictureRepository $pictureRepository)
    {
        $this->workerServer = (string) getenv('WORKER_SERVER');
        $this->workerPort = (int) getenv('WORKER_PORT');
        $this->workerAddress = $this->workerServer . ":" . $this->workerPort;
        $this->logger = $logger;
        $this->labRepository = $labRepository;
        $this->pictureRepository = $pictureRepository;
    }

    /**
     * 
     * @Rest\Get("/api/labs/{id<\d+>}/pictures", name="api_get_pictures")
     * 
     */
    public function indexAction(int $id, Request $request, UserRepository $userRepository)
    {
        $pictures = $this->pictureRepository->findByLab($id);
        $data = [];
        foreach($pictures as $picture){

            $data[$picture->getId()] = [
                "id"=> $picture->getId(),
                "name"=> $picture->getName(),
                "type"=> $picture->getType(),
                "width"=> $picture->getWidth(),
                "height"=> $picture->getHeight(),
            ];
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed pictures (60028).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * 
     * @Rest\Get("/api/labs/{labId<\d+>}/pictures/{id<\d+>}", name="api_get_picture")
     */
    public function showAction(
        int $labId,
        int $id,
        Request $request,
        UserInterface $user,
        PictureRepository $pictureRepository,
        SerializerInterface $serializer)
    {
        //$lab = $labRepository->findById($LabId);
        $picture = $pictureRepository->findByIdAndLab($id, $labId);

        $response = new Response();

        if($picture === null) {
            $response->setContent(json_encode([
                'code' => 404,
                'status'=>'fail',
                'message' => 'Picture "'.$id.'" not found on lab "'.$labId.'".']));
        }
        else {
            $data = [
                "id"=> $picture->getId(),
                "name"=> $picture->getName(),
                "type"=> $picture->getType(),
                "width"=> $picture->getWidth(),
                "height"=> $picture->getHeight(),
                "map" => $picture->getMap()
            ];
           
            $response->setContent(json_encode([
                'code' => 200,
                'status'=>'success',
                'message' => 'Picture loaded',
                'data' =>$data]));
        }
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * 
     * @Rest\Get("/api/labs/{labId<\d+>}/pictures/{id<\d+>}/data", name="api_get_picture_data")
     */
    public function getPictureData(
        int $labId,
        int $id,
        Request $request,
        UserInterface $user,
        PictureRepository $pictureRepository,
        SerializerInterface $serializer)
    {
        $picture = $pictureRepository->findByIdAndLab($id, $labId);

        $response = new Response();

        if($picture === null) {
            $response->setContent(json_encode([
                'code' => 404,
                'status'=>'fail',
                'message' => 'Picture "'.$id.'" not found on lab "'.$labId.'".']));
                return $response;
        }

        $height = $picture->getHeight();
		$width = $picture->getWidth();
		if ($request->query->get('width') > 0) {
			$width = $request->query->get('width');
		}
		if ($request->query->get('height')) {
			$height = $request->query->get('height');
		}

        $fileName = $picture->getName() . "." . explode('image/', $picture->getType())[1];
        $file = '/opt/remotelabz/public/editor/images/pictures/'.$fileName;

        /*$thumb = imagecreatetruecolor($width, $height);
        if($picture->getType() ==  "image/png") {
            $source = imagecreatefrompng('/opt/remotelabz/public/editor/images/pictures/'.$fileName);
        }
        else {
            $source = imagecreatefromjpeg('/opt/remotelabz/public/editor/images/pictures/'.$fileName);
        }
        // Resize
        imagecopyresized($thumb, $source, 0, 0, 0, 0, $width, $height, $picture->getWidth(), $picture->getHeight());*/        

        $response = new BinaryFileResponse($file);
        return $response;
    }

    /**
     * 
     * @Rest\Post("/api/labs/{id<\d+>}/pictures", name="api_new_picture")
     */
    public function newAction(Request $request, int $id)
    {
        $picture = new Picture();
        $lab = $this->labRepository->findById($id);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        $data = $_POST;
        if (!empty($_FILES)) {
			foreach ($_FILES as $file) {
				if (file_exists($file['tmp_name'])) {
					$fp = fopen($file['tmp_name'], 'r');
					$size = filesize($file['tmp_name']);
					if ($fp !== False) {
						//$finfo = new finfo(FILEINFO_MIME);
						$data['data'] = fread($fp, $size);
                        $data['type'] = mime_content_type($file['tmp_name']);
					}
				}
			}
		}
        if($data['type'] !== 'image/png' && $data['type'] !== 'image/jpeg') {
            $response->setContent(json_encode([
                'code' => 400,
                'status'=> 'fail',
                'message' => 'This is not a valid picture type']));
            return $response;
        }
        $type = explode("image/",$data['type'])[1];
        $picture->setLab($lab);
        $picture->setName($data['name']);
        $picture->setType($data['type']);
        $picture->setData($data['data']);
        file_put_contents('/opt/remotelabz/public/editor/images/pictures/'.$data['name'].'.'.$type, $data['data']);

        list($width, $height) = getimagesizefromstring($data['data']);
        $picture->setWidth($width);
        $picture->setHeight($height);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($picture);
        $entityManager->flush();


        $this->logger->info("Picture named" . $picture->getName() . " created");

       /* if ('json' === $request->getRequestFormat()) {
            return $this->json($textobject, 200, [], ['api_get_textobjects']);
        }*/

       /* return $this->redirectToRoute('edit_lab', [
            'id' => $lab->getId()
        ]);*/
        
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        return $response;
    }

    /**
     * 
     * @Rest\Put("/api/labs/{labId<\d+>}/pictures/{id<\d+>}", name="api_update_picture")
     */
    public function updateAction(Request $request, int $id, int $labId, PictureRepository $pictureRepository)
    {
        $picture = $pictureRepository->findByIdAndLab($id, $labId);
        $data = json_decode($request->getContent(), true);   

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        if(isset($data['name']) && $data['name'] == '') {
            $response->setContent(json_encode([
                'code' => 400,
                'status'=> 'fail',
                'message' => 'The picture is not valid']));
            return $response;
        }
        if(isset($data['name']) && $data['name'] !== $picture->getName()){
            $type = explode("image/",$picture->getType())[1];
            unlink('/opt/remotelabz/public/editor/images/pictures/'.$picture->getName().'.'.$type);
            file_put_contents('/opt/remotelabz/public/editor/images/pictures/'.$data['name'].'.'.$type, $picture->getData());
            $picture->setName($data['name']);
        }
        $picture->setMap($data['map']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($picture);
        $entityManager->flush();


        $this->logger->info("Picture named" . $picture->getName() . " modified");
        
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        return $response;
    }

    /**
     * 
     * @Rest\Delete("/api/labs/{labId<\d+>}/pictures/{id<\d+>}", name="api_delete_picture")
     */
    public function deleteAction(ManagerRegistry $doctrine, Request $request, int $id, int $labId, PictureRepository $pictureRepository)
    {
        $picture = $pictureRepository->findByIdAndLab($id, $labId);
        
        $fileName = $picture->getName() . "." . explode('image/', $picture->getType())[1];
        unlink('/opt/remotelabz/public/editor/images/pictures/'.$fileName);

        $entityManager = $doctrine->getManager();
        $entityManager->remove($picture);
        $entityManager->flush();

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=>'success',
            'message' => 'Lab has been saved (60023).'
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }
}
