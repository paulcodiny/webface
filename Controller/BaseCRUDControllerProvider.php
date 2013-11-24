<?php

namespace WebFace\Controller;

use Silex\ControllerCollection;
use Symfony\Component\Form\Form;
use WebFace\CurrentServiceContainer;
use WebFace\Definition;
use WebFace\Entity\EntityManager;
use WebFace\Form\Type\EmbeddedHasManyFormType;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\InvalidConfigurationException;

use Doctrine\Common\Util\Inflector;

use Silex\Application;
use Silex\ControllerProviderInterface;

use LogicException;
use WebFace\ListControl\FilterBuilder;
use WebFace\ListControl\ListBuilder;


/**
 * Class BaseCRUDControllerProvider
 * @package WebFace\Controller
 */
class BaseCRUDControllerProvider implements ControllerProviderInterface
{
    /**
     * Caches method getTable, only read
     * @var null|string
     */
    protected $table = null;
    protected $flashName = 'wf_flash';

    /** @var Definition */
    public $currentDefinition;

    public function __construct()
    {
        $this->table = $this->getTable();
    }

    public function connect(Application $app)
    {
        $t = clone $this;

        /** @var ControllerCollection $controllers */
        $controllers = $app['controllers_factory'];

        $controllers->before(function() use ($app, $t) {
            $t->beforeAction($app);
        });

        $controllers->get('/new', function(Application $app) use ($t) {
            return $t->actionNew($app);
        })->bind($this->table . '_new');

        $controllers->post('/new', function(Application $app) use ($t) {
            return $t->actionCreate($app);
        })->bind($this->table . '_create');

        $controllers->get('/{id}/edit', function(Application $app, $id) use ($t) {
            return $t->actionEdit($app, $id);
        })->bind($this->table . '_edit');

        $controllers->post('/{id}/edit', function(Application $app, $id) use ($t) {
            return $t->actionUpdate($app, $id);
        })->bind($this->table . '_update');

        $controllers->post('/{id}/delete', function(Application $app, $id) use ($t) {
            return $t->actionDelete($app, $id);
        })->bind($this->table . '_delete');

        $controllers->get('/{page}', function(Application $app, $page) use ($t) {
            return $t->actionList($app, $page);
        })->bind($this->table . '_list')->assert('page', '\d+')->value('page', 1);

        $app['twig.loader.filesystem']->addPath(realpath(__DIR__ . '/../View'));
        $app['twig.loader.filesystem']->addPath(realpath(__DIR__ . '/../View/crud'));
        $app['twig.loader.filesystem']->addPath(realpath(__DIR__ . '/../View/partials'));

        return $controllers;
    }

    public function beforeAction(Application $app)
    {
        $this->currentDefinition = $app['webface.admin.definition.' . $this->table];

        $flash = $app['session']->get($this->flashName);

        if (!empty($flash)) {
            $app['session']->set($this->flashName, null);
            $app['twig']->addGlobal($this->flashName, $flash);
        }

        $app['webface.admin']->setCurrentTable($this->table);

        $app['twig']->addGlobal('webface_current_table_label', $app['webface.admin']->getCurrentTableLabel());

        $token = $app['security']->getToken();
        if (!empty($token)) {
            $user = $token->getUser();
            $app['twig']->addGlobal('webface_username', $user->getUsername());
        }
    }

    public function afterAction()
    {

    }


    /**
     * @return string
     */
    public function getTable()
    {
        $fullClassName = get_class($this);
        if (strpos($fullClassName, '\\') !== false) {
            $fullClassNamePathes = explode('\\', $fullClassName);
            $className = $fullClassNamePathes[count($fullClassNamePathes) - 1];
        } else {
            $className = $fullClassName;
        }

        $modelName = str_replace('CRUDControllerProvider', '', $className);

        return Inflector::tableize($modelName);
    }

    /**
     * Callback called after entity created
     * @param Application $app
     * @param int         $entityId
     * @param array       $data
     */
    public function afterCreated(Application $app, $entityId, $data)
    {

    }

    /**
     * Callback called after entity updated (not created)
     * @param Application $app
     * @param array       $oldData Whole object data, including `id`
     * @param array       $newData Only data which can be updated (list of fields from getFormFieldNames)
     */
    public function afterUpdated(Application $app, $oldData, $newData)
    {

    }

    public function afterDeleted(Application $app, $data)
    {

    }

    public function actionList(Application $app, $page)
    {
        $fields = $this->currentDefinition->getListFieldsDefinition();

        $filterWhereClause = $this->currentDefinition->getFilterBuilder()->buildFilterQuery();

        // pagination here
        $where = $filterWhereClause ? 'WHERE ' . $filterWhereClause : '';
        $orderBy = 'ORDER BY ' . $this->currentDefinition->getListOrder();

        $queryCount = "SELECT COUNT(*) AS `count` FROM `{$this->table}` {$where};";
        $count = $app['db']->fetchAssoc($queryCount);
        $count = $count['count'];

        $perPage = $this->currentDefinition->getListPerPageCount();
        $maxPage = max(ceil($count * 1.0 / $perPage * 1.0), 1);
        if ($page < 1) {
            $page = 1;
        }

        if ($page > $maxPage) {
            $page = $maxPage;
        }

        $offset = ($page - 1) * $perPage;

        $entities = $this->currentDefinition->getEntityManager()->getEntitiesList($where, $orderBy, $offset, $perPage);

        // pagination end

        $renderedFields = array();

        // prepare list_edit fields
        $listEditFields = array();
        foreach ($fields as $fieldName => $field) {
            if (isset($field['config']['list_edit']) && $field['config']['list_edit']) {
                $listEditFields[$fieldName] = $field;

                $renderedFields[] = $fieldName;
            }
        }

        if (count($listEditFields)) {
            $app['twig']->addGlobal('webface_need_list_inline_edit', true);
            foreach ($entities as $k => $entity) {
                $builder = $app['form.factory']->createNamedBuilder('entity', 'form', $entity, array('csrf_protection' => false));
                $this->currentDefinition->getFormBuilder()->addFieldsToBuilder($listEditFields, $builder, $entity);
                $entities[$k]['list_edit_form'] = $builder->getForm()->createView();
            }
        }

        // prepare list_display fields
        foreach ($fields as $fieldName => $field) {
            if (isset($field['config']['list_display'])) {
                foreach ($entities as $k => $entity) {
                    $entities[$k][$fieldName] = $this->currentDefinition->getListBuilder()->$field['config']['list_display']($entity);
                }

                $renderedFields[] = $fieldName;
            }
        }

        // prepare relation fields
        foreach ($fields as $fieldName => $field) {
            if ($field['type'] == 'relation' && !in_array($fieldName, $renderedFields)) {
                $config = $field['config'];
                if ($config['relation_type'] == 'belongs_to') {
                    if (!isset($config['relation_field'])) {
                        $config['relation_field'] = 'id';
                    }
                    $query = "SELECT {$config['relation_field']}, {$config['relation_display']}
                      FROM {$config['relation_table']} WHERE {$config['relation_field']} = ? LIMIT 1";

                    foreach ($entities as $k => $entity) {
                        $relationEntity = $app['db']->fetchAssoc($query, array($entity[$fieldName]));
                        $entities[$k]['relation:belongs_to:display:' . $fieldName] = $relationEntity[$config['relation_display']];
                    }
                }
            }
        }

        $filter = $this->currentDefinition->getFilterBuilder()->getForm();
        if ($filter->count()) {
            $app['twig']->addGlobal('webface_need_list_filter', true);
        }

        // prepare entity actions
        foreach ($entities as $k => $entity) {
            $entities[$k]['actions'] = $this->currentDefinition->getListBuilder()->getEntityActions($entity);
        }

        return $app['twig']->render('list.twig', array(
            'table'      => $this->table,
            'fields'     => $fields,
            'filter'     => $filter->createView(),
            'entities'   => $entities,
            'actions'    => $this->currentDefinition->getListBuilder()->getActions(),
            'paging'     => array(
                'current_page' => $page,
                'max_page'     => $maxPage,
                'is_prev'      => $page != 1,
                'is_next'      => $page != $maxPage,
                'http_query'   => http_build_query($app['request']->query->all()),
            ),
        ));
    }

    public function actionNew(Application $app)
    {
        $form = $this->currentDefinition->getFormBuilder()->getForm();

        return $app['twig']->render('new.twig', array(
            'table' => $this->table,
            'form'  => $form->createView(),
            'ajax'  => $app['request']->isXmlHttpRequest(),
        ));
    }

    public function actionCreate(Application $app)
    {
        $form = $this->currentDefinition->getFormBuilder()->getForm();

        $form->handleRequest($app['request']);

        if ($form->isValid()) {
            $data = $this->currentDefinition->getFormBuilder()->prepareFormToStore($form);

            $app['db']->insert($this->table, $data);
            $entityId = $app['db']->lastInsertId();

            $this->currentDefinition->getEntity()->saveRelationFields($entityId, $form);

            $this->afterCreated($app, $entityId, $data);

            $app['session']->set($this->flashName, array(
                'type' => 'success',
                'text' => 'Запись успешно добавлена!',
            ));

            switch ($app['request']->get('next', 'new')) {
                default:
                case 'new':
                    return $app->redirect($app['url_generator']->generate($this->table . '_new'));
                case 'list':
                    return $app->redirect($app['url_generator']->generate($this->table . '_list'));
            }
        } else {
            $app['session']->set($this->flashName, array(
                'type' => 'error',
                'text' => 'Внимание, поправьте ошибки ниже!',
            ));
        }

        return $app['twig']->render('new.twig', array(
            'table' => $this->table,
            'form'  => $form->createView(),
        ));
    }

    public function actionEdit(Application $app, $id)
    {
        $entity = $this->currentDefinition->getEntityManager()->getEntity($id);

        $form = $this->currentDefinition->getFormBuilder()->getForm($entity);

        return $app['twig']->render('edit.twig', array(
            'table'  => $this->table,
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    public function actionUpdate(Application $app, $id)
    {
        $entity = $this->currentDefinition->getEntityManager()->getEntity($id);

        $form = $this->currentDefinition->getFormBuilder()->getForm($entity);

        $success = array(
            'type' => 'success',
            'text' => 'Запись успешно отредактирована!',
        );

        $error = array(
            'type' => 'error',
            'text' => 'Внимание, возникли какие-то ошибки!',
        );

        // Processing AJAX-editing
        if ($app['request']->isXmlHttpRequest()) {
            $result = $this->ajaxUpdate($app, $entity, $form);

            return $app->json($result ? $success : $error);
        }

        $form->handleRequest($app['request']);

        if ($form->isValid()) {
            $data = $this->currentDefinition->getFormBuilder()->prepareFormToStore($form);
            $entityId = $this->currentDefinition->getEntityManager()->getEntityId($entity);
            $app['db']->update($this->table, $data, $entityId);
            $this->currentDefinition->getEntity()->saveRelationFields($id, $form);
            $this->afterUpdated($app, $entity, $data);

            $app['session']->set($this->flashName, $success);

            switch ($app['request']->get('next', 'edit')) {
                default:
                case 'edit':
                    return $app->redirect($app['url_generator']->generate($this->table . '_edit', array('id' => $entity['id'])));
                case 'list':
                    return $app->redirect($app['url_generator']->generate($this->table . '_list'));
            }

        } else {
            $app['session']->set($this->flashName, $error);
        }

        return $app['twig']->render('edit.twig', array(
            'table'  => $this->table,
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    public function ajaxUpdate(Application $app, $entity, Form $form)
    {
        $data = array_merge($entity, $app['request']->get('entity'));

        // OH GOD, symfony2 forms converts all values except NULL in true in checkboxes
        // so make them NULL
        foreach ($this->currentDefinition->getListFieldsDefinition() as $fieldName => $field) {
            if (isset($field['config']['list_edit']) && $field['config']['list_edit']
                    && $field['type'] == 'boolean') {
                $data[$fieldName] = $data[$fieldName] ? $data[$fieldName] : null;
            }
        }

        $form->submit($data);
        if ($form->isValid()) {
            $entityId = $this->currentDefinition->getEntityManager()->getEntityId($entity);
            $app['db']->update($this->table, $this->currentDefinition->getFormBuilder()->prepareFormToStore($form), $entityId);
            $this->afterUpdated($app, $entity, $data);
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }

    public function actionDelete(Application $app, $id)
    {
        $entityManager = $this->currentDefinition->getEntityManager();
        $entity   = $entityManager->getEntity($id);
        $entityId = $entityManager->getEntityId($entity);

        $app['db']->delete($this->table, $entityId);
        $this->currentDefinition->getEntity()->deleteRelationEntities($entity);
        $this->afterDeleted($app, $entity);

        // @todo Flashes about deleting are not displayed, fix it
        $app['session']->set($this->flashName, array(
            'type' => 'success',
            'text' => 'Запись успешно удалена!',
        ));

        return $app->redirect($app['url_generator']->generate($this->table . '_list'));
    }
}