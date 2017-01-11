<?php
namespace Boxspaced\CmsSearchModule\Controller;

use Exception as PhpException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container as SessionContainer;
use Zend\View\Model\ViewModel;
use Zend\Log\Logger;
use Boxspaced\CmsSearchModule\Service;
use Zend\Paginator;
use Zend_Search_Lucene as Search;
use Zend_Search_Lucene_Search_QueryParser as SearchQueryParser;
use Boxspaced\CmsAccountModule\Service\AccountService;
use Boxspaced\CmsBlockModule\Service\BlockService;
use Boxspaced\CmsCoreModule\Service\ModulePageService;
use Boxspaced\CmsItemModule\Service\ItemService;
use Boxspaced\CmsCoreModule\Form\ModulePagePublishForm;
use Boxspaced\CmsCoreModule\Service\ModulePagePublishingOptions;
use Boxspaced\CmsCoreModule\Service\FreeBlock;
use Boxspaced\CmsCoreModule\Service\BlockSequence;
use Boxspaced\CmsCoreModule\Service\BlockSequenceBlock;

class SearchController extends AbstractActionController
{

    /**
     * @var ModulePageService
     */
    protected $modulePageService;

    /**
     * @var BlockService
     */
    protected $blockService;

    /**
     * @var ItemService
     */
    protected $itemService;

    /**
     * @var AccountService
     */
    protected $accountService;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var SessionContainer
     */
    protected $previewSession;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var ViewModel
     */
    protected $view;

    /**
     * @param ModulePageService $modulePageService
     * @param BlockService $blockService
     * @param ItemService $itemService
     * @param AccountService $accountService
     * @param Logger $logger
     * @param array $config
     */
    public function __construct(
        ModulePageService $modulePageService,
        BlockService $blockService,
        ItemService $itemService,
        AccountService $accountService,
        Logger $logger,
        array $config
    )
    {
        $this->modulePageService = $modulePageService;
        $this->blockService = $blockService;
        $this->itemService = $itemService;
        $this->accountService = $accountService;
        $this->logger = $logger;
        $this->config = $config;

        $this->previewSession = new SessionContainer('preview');

        $this->view = new ViewModel();
    }

    /**
     * @return void
     */
    protected function initBackendAction()
    {
        if ($this->config['core']['has_ssl']) {
            $this->forceHttps();
        }
        $this->view->setTerminal(true);
    }

    /**
     * @return void
     */
    public function simpleAction()
    {
        $preview = $this->params()->fromQuery('preview');
        $modulePageId = 8;

        $this->layout()->navText = 'Search Results';
        $this->layout()->isStandalone = true;
        $this->view->query = $this->getRequest()->getQuery();

        $canPublish = $this->accountService->isAllowed(get_class(), 'publish');

        if ('publishing' === $preview && $canPublish) {

            // Previewing publishing
            $publishingOptions = $this->previewSession->publishing;

        } else {

            $publishingOptions = $this->modulePageService->getCurrentPublishingOptions($modulePageId);

            $adminNavigation = $this->adminNavigationWidget(true);
            if (null !== $adminNavigation) {
                $this->layout()->addChild($adminNavigation, 'adminNavigation');
            }

            $modulePageAdmin = $this->modulePageAdminWidget(
                'search',
                'simple',
                $modulePageId,
                true
            );
            if (null !== $modulePageAdmin) {
                $this->layout()->addChild($modulePageAdmin, 'adminPanel');
            }
        }

        $query = $this->params()->fromQuery('q') ?: '';

        try {

            $index = Search::open($this->config['search']['index_path']);
            $query = SearchQueryParser::parse($query);
            $hits = $index->find($query);

        } catch (PhpException $e) {

            $this->logger->debug("Site search failed with query '{$query}' and error '{$e->getMessage()}'");
            $hits = [];
        }

        $adapter = new Paginator\Adapter\ArrayAdapter($hits);
        $paginator = new Paginator\Paginator($adapter);
        $paginator->setCurrentPageNumber($this->params()->fromQuery('page', 1));
        $paginator->setItemCountPerPage($this->config['search']['show_per_page']);
        $this->view->paginator = $paginator;

        $results = [];

        foreach ($paginator as $hit) {

            if ($hit->module !== 'item') {
                continue;
            }

            try {

                $item = $this->itemService->getCacheControlledItem($hit->contentId);
                $itemMeta = $this->itemService->getCacheControlledItemMeta($hit->contentId);
                $itemType = $this->itemService->getType($itemMeta->typeId);
                $publishingOptions = $this->itemService->getCurrentPublishingOptions($hit->contentId);

            } catch (PhpException $e) {

                $this->logger->warn('Failed to get item for simple search results: ' . $e->getMessage());
                continue;
            }

            foreach ($itemType->teaserTemplates as $template) {

                if ($template->id == $publishingOptions->teaserTemplateId) {
                    $teaserTemplate = $template;
                    break;
                }
            }

            if (!isset($teaserTemplate)) {

                $this->logger->warn('Teaser template not found in simple search results, skipping item');
                continue;
            }

            $values = [];

            foreach ($item->fields as $itemField) {
                $values[$itemField->name] = $itemField->value;
            }

            foreach ($item->parts[0]->fields as $partField) {
                $values[$partField->name] = $partField->value;
            }

            $values['title'] = $item->title;
            $values['name'] = $itemMeta->name;

            $result = (new ViewModel($values))->setTemplate(sprintf(
                'boxspaced/cms-item-module/item/%s.phtml',
                str_replace('_', '', $teaserTemplate->viewScript)
            ));

            $this->view->addChild($result, 'results', true);
        }

        $this->modulePageBlocks($this->view, $publishingOptions);

        return $this->view;
    }

    /**
     * @return void
     */
    public function publishAction()
    {
        $this->initBackendAction();

        $id = $this->params()->fromRoute('id');
        $modulePage = $this->modulePageService->getModulePage($id);

        $this->view->moduleName = 'search';
        $this->view->pageName = $modulePage->name;

        $form = new ModulePagePublishForm(
            $id,
            $this->modulePageService,
            $this->blockService
        );
        $form->get('id')->setValue($id);
        $form->get('from')->setValue($this->params()->fromQuery('from'));

        $this->view->form = $form;

        if (!$this->getRequest()->isPost()) {

            $currentPublishingOptions = $this->modulePageService->getCurrentPublishingOptions($id);
            $form->populateFromPublishingOptions($currentPublishingOptions);
            return $this->view;
        }

        $form->setData($this->getRequest()->getPost());

        if ($this->params()->fromPost('partial')) {

            $form->get('partial')->setValue(false);
            return $this->view;
        }

        if (!$form->isValid()) {

            $this->flashMessenger()->addErrorMessage('Validation failed.');
            return $this->view;
        }

        $values = $form->getData();

        $publishingOptions = new ModulePagePublishingOptions();

        foreach ($values['freeBlocks'] as $name => $block) {

            $freeBlock = new FreeBlock();
            $freeBlock->name = $name;
            $freeBlock->id = $block['id'];

            $publishingOptions->freeBlocks[] = $freeBlock;
        }

        foreach ($values['blockSequences'] as $name => $sequence) {

            $blockSequence = new BlockSequence();
            $blockSequence->name = $name;

            foreach ($sequence['blocks'] as $key => $block) {

                if (is_numeric($key)) {

                    $blockSequenceBlock = new BlockSequenceBlock();
                    $blockSequenceBlock->id = $block['id'];
                    $blockSequenceBlock->orderBy = $block['orderBy'];
                    $blockSequence->blocks[] = $blockSequenceBlock;
                }
            }

            $publishingOptions->blockSequences[] = $blockSequence;
        }

        if (null !== $values['publish']) {

            $this->modulePageService->publish($id, $publishingOptions);

            $this->flashMessenger()->addSuccessMessage('Publishing successful.');

            return $this->redirect()->toRoute('search', [
                'action' => $modulePage->name,
            ]);

        } else {

            // Preview
            $this->previewSession->publishing = $publishingOptions;
            $this->view->preview = true;
        }

        return $this->view;
    }

}
