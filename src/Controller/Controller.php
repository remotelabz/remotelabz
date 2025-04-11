<?php

namespace App\Controller;

use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use App\Service\Network\NetworkManager;

/**
 * Provides custom JSON handling using JMSSerializer.
 *
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class Controller extends AbstractFOSRestController
{
    public static function getSubscribedServices(): array
    {
        return array_merge([
            'jms_serializer' => '?' . SerializerInterface::class,
        ], parent::getSubscribedServices());
    }

    /**
     * Returns a JsonResponse that uses JMSSerializer component.
     */
    protected function json($data = '', int $status = 200, array $headers = [], array $context = [], bool $json = false): JsonResponse
    {
        $serializationContext = SerializationContext::create();
        
        if (null === $data) {
            $data = '';
        }

        if (empty($data) && $status < 300) {
            $status = 204;
        }

        if (!empty($context)) {
            $serializationContext->setGroups($context);
        }

        if (!$json) {
            $data = $this->container->get('jms_serializer')->serialize($data, 'json', $serializationContext);
        }

        return new JsonResponse($data, $status, $headers, true);
    }

    #[Route(path: '/', name: 'index')]
    public function defaultAction()
    {
        return $this->render('dashboard/index.html.twig');
    }

    #[Route(path: '/admin', name: 'admin')]
    public function adminAction()
    {
        return $this->render('dashboard/index.html.twig');
    }
    

    #[Route(path: '/react/{reactRouting}', name: 'index_react', defaults: ['reactRouting' => 'null'])]
    public function defaultReactAction()
    {
        return $this->render('react.html.twig');
    }

    #[Route(path: '/icon', name: 'icon')]
    public function defaulticonAction()
    {
        // To obtain this list from the bash
        // grep -oP 'id="[a-z-]+"' icons.svg | sed -r 's/id=("[a-z-]*)/\1/g' | tr '\n' ','
        $list=array("os","abuse","accessibility","account","admin","angle-double-left","angle-double-right","angle-down","angle-left","angle-right","angle-up","api","appearance","applications","approval","approval-solid","archive","arrow-down","arrow-left","arrow-right","arrow-up","assignee","at","autoplay","bitbucket","bold","book","bookmark","branch","branch-deleted","brand-zoom","bug","bulb","bullhorn","calendar","cancel","canceled-circle","car","chart","check","check-circle","check-circle-filled","cherry-pick-commit","chevron-double-lg-left","chevron-double-lg-right","chevron-down","chevron-left","chevron-lg-down","chevron-lg-left","chevron-lg-right","chevron-lg-up","chevron-right","chevron-up","clear","clear-all","clock","close","cloud-gear","code","collapse","collapse-left","collapse-right","comment","comment-dots","comment-next","comments","commit","comparison","container-image","copy-to-clipboard","credit-card","dash","dashboard","disk","doc-changes","doc-chart","doc-code","doc-compressed","doc-expand","doc-image","doc-new","doc-symlink","doc-text","doc-versions","document","documents","dotted-circle","double-headed-arrow","download","drag","drag-horizontal","drag-vertical","dumbbell","duplicate","earth","environment","epic","epic-closed","error","expand","expand-down","expand-left","expand-right","expand-up","expire","export","external-link","eye","eye-slash","feature-flag","feature-flag-disabled","file-addition","file-addition-solid","file-additions-solid","file-deletion","file-deletion-solid","file-modified","file-modified-solid","file-tree","filter","fire","first-contribution","flag","folder","folder-new","folder-o","folder-open","food","fork","git-merge","github","go-back","google","group","hamburger","heading","heart","history","home","hook","hourglass","image-comment-dark","image-comment-light","import","incognito","information","information-o","infrastructure-registry","issue-block","issue-close","issue-closed","issue-new","issue-open-m","issues","italic","iteration","key","label","labels","leave","level-up","license","link","linkedin","list-bulleted","list-indent","list-numbered","list-outdent","list-task","live-preview","location","location-dot","lock","lock-open","log","long-arrow","mail","marquee-selection","maximize","media","media-broken","merge","merge-request","merge-request-close","merge-request-close-m","merge-request-open","messages","minimize","mobile","mobile-issue-close","monitor","monitor-lines","monitor-o","namespace","nature","notifications","notifications-off","object","overview","package","paper-airplane","paperclip","pause","pencil","pencil-square","pipeline","planning","play","plus","plus-square","plus-square-o","pod","podcast","power","preferences","profile","progress","project","push-rules","question","question-o","quote","redo","remove","remove-all","repeat","requirements","retry","review-checkmark","review-list","review-warning","rocket","rss","scale","scroll-handle","search","search-dot","search-minus","search-plus","settings","severity-critical","severity-high","severity-info","severity-low","severity-medium","severity-unknown","share","shield","skype","slight-frown","slight-smile","smart-card","smile","smiley","snippet","soft-unwrap","soft-wrap","sort-highest","sort-lowest","spam","spinner","stage-all","staged","star","star-o","status","status-health","stop","strikethrough","subgroup","substitute","symlink","table","tablet","tachometer","tag","tanuki","tanuki-verified","task-done","template","terminal","text-description","thumb-down","thumb-up","thumbtack","time-out","timer","todo-add","todo-done","token","trigger-source","twitter","unapproval","unassignee","underline","unlink","unstage-all","unstaged","upload","user","users","volume-up","warning","warning-solid","weight","work");
        return $this->render('dashboard/icon.html.twig', [
            'list' => $list

        ]);

    }

}
