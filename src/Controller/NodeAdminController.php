<?php

namespace NS\KunstmaanNodeBundle\Controller;

use DateTime;
use Kunstmaan\AdminBundle\Helper\FormWidgets\FormWidget;
use Kunstmaan\AdminBundle\Helper\FormWidgets\Tabs\Tab;
use Kunstmaan\AdminBundle\Helper\FormWidgets\Tabs\TabPane;
use Kunstmaan\AdminBundle\Helper\Security\Acl\Permission\PermissionMap;
use Kunstmaan\NodeBundle\Event\AdaptFormEvent;
use Kunstmaan\NodeBundle\Event\Events;
use Kunstmaan\NodeBundle\Event\NodeEvent;
use Kunstmaan\NodeBundle\Form\NodeMenuTabTranslationAdminType;
use Kunstmaan\UtilitiesBundle\Helper\ClassLookup;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Kunstmaan\NodeBundle\Entity\Node;

/**
 * NodeAdminController
 */
class NodeAdminController extends \Kunstmaan\NodeBundle\Controller\NodeAdminController
{
    //Stupid multiple routing rules with same format, need to preserve load order
    
    /**
     * @param int $id The node id
     *
     * @throws AccessDeniedException
     * @Route("/{id}/copyfromotherlanguage", requirements={"_method" = "GET", "id" = "\d+"}, name="KunstmaanNodeBundle_nodes_copyfromotherlanguage")
     * @Template()
     *
     * @return RedirectResponse
     */
    public function copyFromOtherLanguageAction($id)
    {
        return parent::copyFromOtherLanguageAction($id);
    }
    
    /**
     * @param int $id
     *
     * @throws AccessDeniedException
     * @Route("/{id}/createemptypage", requirements={"_method" = "GET", "id" = "\d+"}, name="KunstmaanNodeBundle_nodes_createemptypage")
     * @Template()
     *
     * @return RedirectResponse
     */
    public function createEmptyPageAction($id)
    {
        return parent::createEmptyPageAction($id);
    }
    
    /**
     * @param int $id
     *
     * @throws AccessDeniedException
     * @Route("/{id}/publish", requirements={"_method" = "GET|POST", "id" = "\d+"}, name="KunstmaanNodeBundle_nodes_publish")
     *
     * @return RedirectResponse
     */
    public function publishAction($id)
    {
        return parent::publishAction($id);
    }
    
    /**
     * @param int $id
     *
     * @throws AccessDeniedException
     * @Route("/{id}/unpublish", requirements={"_method" = "GET|POST", "id" = "\d+"}, name="KunstmaanNodeBundle_nodes_unpublish")
     *
     * @return RedirectResponse
     */
    public function unPublishAction($id)
    {
        return parent::unPublishAction($id);
    }
    
    /**
     * @param int $id
     *
     * @throws AccessDeniedException
     * @Route("/{id}/unschedulepublish", requirements={"_method" = "GET|POST", "id" = "\d+"}, name="KunstmaanNodeBundle_nodes_unschedule_publish")
     *
     * @return RedirectResponse
     */
    public function unSchedulePublishAction($id)
    {
        return parent::unSchedulePublishAction($id);
    }
    
    /**
     * @param int $id
     *
     * @throws AccessDeniedException
     * @Route("/{id}/delete", requirements={"_method" = "POST", "id" = "\d+"}, name="KunstmaanNodeBundle_nodes_delete")
     * @Template()
     *
     * @return RedirectResponse
     */
    public function deleteAction($id)
    {
        return parent::deleteAction($id);
    }
    
    /**
     * @param int $id The node id
     *
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @Route("/{id}/revert", requirements={"_method" = "GET", "id" = "\d+"}, defaults={"subaction" = "public"}, name="KunstmaanNodeBundle_nodes_revert")
     * @Template()
     *
     * @return RedirectResponse
     */
    public function revertAction($id)
    {
         return parent::revertAction($id);
    }
    
    /**
     * @param int $id
     *
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @Route("/{id}/add", requirements={"_method" = "POST", "id" = "\d+"}, name="KunstmaanNodeBundle_nodes_add")
     * @Template()
     *
     * @return RedirectResponse
     */
    public function addAction($id)
    {
        return parent::addAction($id);
    }
    
    /**
     * @param int    $id        The node id
     * @param string $subaction The subaction (draft|public)
     *
     * @throws AccessDeniedException
     * @Route("/{id}/{subaction}", requirements={"_method" = "GET|POST", "id" = "\d+"}, defaults={"subaction" = "public"}, name="KunstmaanNodeBundle_nodes_edit")
     * @Template()
     *
     * @return RedirectResponse|array
     */
    public function editAction($id, $subaction)
    {
        $this->init();
        /* @var Node $node */
        $node = $this->em->getRepository('KunstmaanNodeBundle:Node')->find($id);

        $this->checkPermission($node, PermissionMap::PERMISSION_EDIT);

        $request = $this->getRequest();
        $tabPane = new TabPane('todo', $request, $this->container->get('form.factory')); // @todo initialize separate from constructor?

        $nodeTranslation = $node->getNodeTranslation($this->locale, true);
        if (!$nodeTranslation) {
            //try to find a parent node with the correct translation, if there is none allow copy.
            //if there is a parent but it doesn't have the language to copy to don't allow it
            $parentNode = $node->getParent();
            if ($parentNode) {
                $parentNodeTranslation = $parentNode->getNodeTranslation($this->locale, true);
                $parentsAreOk = false;

                if ($parentNodeTranslation) {
                    $parentsAreOk = $this->em->getRepository('KunstmaanNodeBundle:NodeTranslation')->hasParentNodeTranslationsForLanguage($node->getParent()->getNodeTranslation($this->locale, true), $this->locale);
                }
            } else {
                $parentsAreOk = true;
            }

            return $this->render('KunstmaanNodeBundle:NodeAdmin:pagenottranslated.html.twig', array(
                'node' => $node,
                'nodeTranslations' => $node->getNodeTranslations(true),
                'copyfromotherlanguages' => $parentsAreOk
            ));
        }
        
        if(!$node->getInternalName())
            $node->setInternalName($nodeTranslation->getTitle());

        $nodeVersion = $nodeTranslation->getPublicNodeVersion();
        $draftNodeVersion = $nodeTranslation->getNodeVersion('draft');

        /* @var HasNodeInterface $page */
        $page = null;
        $draft = ($subaction == 'draft');
        $saveAsDraft = $request->get('saveasdraft');
        if ((!$draft && !empty($saveAsDraft)) || ($draft && is_null($draftNodeVersion))) {
            // Create a new draft version
            $draft = true;
            $subaction = "draft";
            $page = $nodeVersion->getRef($this->em);
            $nodeVersion = $this->createDraftVersion($page, $nodeTranslation, $nodeVersion);
            $draftNodeVersion = $nodeVersion;
        } elseif ($draft) {
            $nodeVersion = $draftNodeVersion;
            $page = $nodeVersion->getRef($this->em);
        } else {
            if ($request->getMethod() == 'POST') {
                //Check the version timeout and make a new nodeversion if the timeout is passed
                $thresholdDate = date("Y-m-d H:i:s", time()-$this->container->getParameter("kunstmaan_node.version_timeout"));
                $updatedDate = date("Y-m-d H:i:s", strtotime($nodeVersion->getUpdated()->format("Y-m-d H:i:s")));
                if ($thresholdDate >= $updatedDate) {
                    $page = $nodeVersion->getRef($this->em);
                    if ($nodeVersion == $nodeTranslation->getPublicNodeVersion()) {
                        $this->get('kunstmaan_node.admin_node.publisher')->createPublicVersion($page, $nodeTranslation, $nodeVersion, $this->user);
                    } else {
                        $this->createDraftVersion($page, $nodeTranslation, $nodeVersion);
                    }
                }
            }
            $page = $nodeVersion->getRef($this->em);
        }

        $isStructureNode = $page->isStructureNode();

        $menubuilder = $this->get('kunstmaan_node.actions_menu_builder');
        $menubuilder->setActiveNodeVersion($nodeVersion);
        $menubuilder->setIsEditableNode(!$isStructureNode);

        // Building the form
        $propertiesWidget = new FormWidget();
        $pageAdminType = $page->getDefaultAdminType();
        if (!is_object($pageAdminType) && is_string($pageAdminType)) {
            $pageAdminType = $this->container->get($pageAdminType);
        }
        $propertiesWidget->addType('main', $pageAdminType, $page);

        $nodeAdminType = $node->getDefaultAdminType();
        if (!is_object($nodeAdminType) && is_string($nodeAdminType)) {
            $nodeAdminType = $this->container->get($nodeAdminType);
        }
        $propertiesWidget->addType('node', $nodeAdminType, $node);
        $tabPane->addTab(new Tab('Properties', $propertiesWidget));

        // Menu tab
        if (!$isStructureNode) {
            $menuWidget = new FormWidget();
            $menuWidget->addType('menunodetranslation', new NodeMenuTabTranslationAdminType(), $nodeTranslation);
            $menuWidget->addType('menunode', $this->get('kunstmaan_node.form.type.node_menu_tab_admin'), $node);
            $tabPane->addTab(new Tab('Menu', $menuWidget));

            $this->get('event_dispatcher')->dispatch(Events::ADAPT_FORM, new AdaptFormEvent($request, $tabPane, $page, $node, $nodeTranslation, $nodeVersion));
        }
        $tabPane->buildForm();

        if ($request->getMethod() == 'POST') {
            $tabPane->bindRequest($request);

            if ($tabPane->isValid()) {
                $this->get('event_dispatcher')->dispatch(Events::PRE_PERSIST, new NodeEvent($node, $nodeTranslation, $nodeVersion, $page));

                $nodeTranslation->setTitle($page->getTitle());
            
                if ($isStructureNode) {
                    $nodeTranslation->setSlug('');
                }
                
                $this->em->persist($node);
                $this->em->persist($nodeTranslation);
                $nodeVersion->setUpdated(new DateTime());
                $this->em->persist($nodeVersion);
                $tabPane->persist($this->em);
                $this->em->flush();

                $this->get('event_dispatcher')->dispatch(Events::POST_PERSIST, new NodeEvent($node, $nodeTranslation, $nodeVersion, $page));

                $this->get('session')->getFlashBag()->add('success', 'The page has been edited');

                $params = array(
                    'id' => $node->getId(),
                    'subaction' => $subaction,
                    'currenttab' => $tabPane->getActiveTab()
                );
                $params = array_merge($params, $tabPane->getExtraParams($request));

                return $this->redirect($this->generateUrl('KunstmaanNodeBundle_nodes_edit', $params));
            }
        }

        $nodeVersions = $this->em->getRepository('KunstmaanNodeBundle:NodeVersion')->findBy(array('nodeTranslation' => $nodeTranslation), array('updated'=> 'ASC'));
        $queuedNodeTranslationAction = $this->em->getRepository('KunstmaanNodeBundle:QueuedNodeTranslationAction')->findOneBy(array('nodeTranslation' => $nodeTranslation));

        return array(
            'page' => $page,
            'entityname' => ClassLookup::getClass($page),
            'nodeVersions' => $nodeVersions,
            'node' => $node,
            'nodeTranslation' => $nodeTranslation,
            'draft' => $draft,
            'draftNodeVersion' => $draftNodeVersion,
            'subaction' => $subaction,
            'tabPane' => $tabPane,
            'editmode' => true,
            'queuedNodeTranslationAction' => $queuedNodeTranslationAction
        );
    }
    
    private function checkPermission(Node $node, $permission)
    {
        if (false === $this->securityContext->isGranted($permission, $node)) {
            throw new AccessDeniedException();
        }
    }
    
    private function deleteNodeChildren(EntityManager $em, BaseUser $user, $locale, ArrayCollection $children)
    {
        /* @var Node $childNode */
        foreach ($children as $childNode) {
            $childNodeTranslation = $childNode->getNodeTranslation($this->locale, true);
            $childNodeVersion = $childNodeTranslation->getPublicNodeVersion();
            $childNodePage = $childNodeVersion->getRef($this->em);

            $this->get('event_dispatcher')->dispatch(Events::PRE_DELETE, new NodeEvent($childNode, $childNodeTranslation, $childNodeVersion, $childNodePage));

            $childNode->setDeleted(true);
            $this->em->persist($childNode);

            $children2 = $childNode->getChildren();
            $this->deleteNodeChildren($em, $user, $locale, $children2);

            $this->get('event_dispatcher')->dispatch(Events::POST_DELETE, new NodeEvent($childNode, $childNodeTranslation, $childNodeVersion, $childNodePage));
        }
    }
    
    private function createDraftVersion(HasNodeInterface $page, NodeTranslation $nodeTranslation, NodeVersion $nodeVersion)
    {
        $publicPage = $this->get('kunstmaan_admin.clone.helper')->deepCloneAndSave($page);
        /* @var NodeVersion $publicNodeVersion */
        $publicNodeVersion = $this->em->getRepository('KunstmaanNodeBundle:NodeVersion')->createNodeVersionFor($publicPage, $nodeTranslation, $this->user, $nodeVersion->getOrigin(), 'public', $nodeVersion->getCreated());
        $nodeTranslation->setPublicNodeVersion($publicNodeVersion);
        $nodeVersion->setType('draft');
        $nodeVersion->setOrigin($publicNodeVersion);
        $nodeVersion->setCreated(new DateTime());

        $this->em->persist($nodeTranslation);
        $this->em->persist($nodeVersion);
        $this->em->flush();

        $this->get('event_dispatcher')->dispatch(Events::CREATE_DRAFT_VERSION, new NodeEvent($nodeTranslation->getNode(), $nodeTranslation, $nodeVersion, $page));

        return $nodeVersion;
    }
}
