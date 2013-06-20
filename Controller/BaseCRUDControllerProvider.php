<?php

namespace WebFace\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Doctrine\Common\Util\Inflector;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use WebFace\Form\Type\EmbeddedHasManyFormType;


class BaseCRUDControllerProvider implements ControllerProviderInterface
{
    protected $table = null;
    protected $flashName = 'wf_flash';

    public function __construct()
    {
        $this->table = $this->getTable();
    }

    public function connect(Application $app)
    {
        $t = clone $this;

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
        $flash = $app['session']->get($this->flashName);

        if (!empty($flash)) {
            $app['session']->set($this->flashName, null);
            $app['twig']->addGlobal($this->flashName, $flash);
        }

        $app['webface.admin']->setCurrentTable($this->table);

        $app['twig']->addGlobal('webface_current_table_label', $app['webface.admin']->getCurrentTableLabel());
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
     * @return array
     */
    public function getFields()
    {
        return array();
    }

    /**
     * @return array
     */
    public function getListFieldNames()
    {
        return array();
    }

    /**
     * @return array
     */
    public function getFormFieldNames()
    {
        return array();
    }

    /**
     * @return array
     */
    public function getFilterFieldNames()
    {
        return array();
    }

    /**
     * @return array
     */
    public function getFormGroups()
    {
        return array('default' => 'Основное');
    }

    public function getEntityActions($app, $entity)
    {
        return array('_edit' => 'Редактировать', '_delete' => 'Удалить');
    }

    /**
     * Строка с тем, как сортировать данные в списке
     * @return string
     */
    public function getListOrder()
    {
        return 'id DESC';
    }

    public function getListPerPageCount()
    {
        return 20;
    }

    public function describeFields(array $fields)
    {
        $allFields = $this->getFields();
        $describedFields = array();
        foreach ($fields as $field) {
            if (!isset($allFields[$field])) {
                throw new FormException('Field "' . $field . '" does not exist in method getFields()');
            }
            $fieldDescription = $allFields[$field];

            // setDefaults
            switch ($fieldDescription['type']) {
                case 'relation':
                    switch ($fieldDescription['config']['relation_type']) {
                        case 'belongs_to':
                            if (!isset($fieldDescription['config']['relation_field'])) {
                                $fieldDescription['config']['relation_field'] = 'id';
                            }
                            break;
                        case 'has_many':
                            if (!isset($fieldDescription['config']['relation_field'])) {
                                $fieldDescription['config']['relation_field'] = 'id';
                            }
                            break;
                    }
                    break;
            }


            $describedFields[$field] = $fieldDescription;
        }

        return $describedFields;
    }

    public function prepareData(Application $app, $fields, $data)
    {
        foreach ($fields as $fieldName => $field) {
            if (isset($field['config']['default']) && !isset($data[$fieldName])) {
                if (is_callable($field['config']['default'])) {
                    $data[$fieldName] = $field['config']['default']($app);
                } else {
                    $data[$fieldName] = $field['config']['default'];
                }
            }
        }

        return $data;
    }

    public function getForm(Application $app, $data = null)
    {
        $fieldNames = $this->getFormFieldNames();
        $fields = $this->describeFields($fieldNames);

        $data = $this->prepareData($app, $fields, $data);

        $builder = $app['form.factory']->createNamedBuilder('entity', 'form', $data, array('csrf_protection' => false));

        return $this->addFieldsToBuilder($fields, $builder, $app, $data)->getForm();
    }

    /**
     * @param array                $fields
     * @param FormBuilderInterface $builder
     * @param Application          $app
     * @param array                [$data=null]
     *
     * @return FormBuilderInterface
     */
    public function addFieldsToBuilder($fields, FormBuilderInterface $builder, $app, $data = null)
    {
        // check groups
        $defaultGroup = 'default';

        // first add default group so its position will be the first
        $groups = array($defaultGroup => array());
        foreach ($fields as $fieldName => $field) {
            $field['name'] = $fieldName;

            if (!isset($field['config']['fields_group'])) {
                if (isset($field['config'])) {
                    $field['config']['fields_group'] = $defaultGroup;
                } else {
                    $field['config'] = array('fields_group' => $defaultGroup);
                }
            }

            $fieldsGroup = $field['config']['fields_group'];
            if (!isset($groups[$fieldsGroup])) {
                $groups[$fieldsGroup] = array();
            }

            $groups[$fieldsGroup][$fieldName] = $field;
        }

        if (!count($groups[$defaultGroup])) {
            unset($groups[$defaultGroup]);
        }

        foreach ($fields as $fieldName => $field) {
            $field['name'] = $fieldName;
            $this->addFieldToBuilder($field, $builder, $app, $data);
        }
        
        // if there is one group - push all fields to the root
        if (count($groups) > 1) {
            $app['twig']->addGlobal('webface_need_groups', $this->getFormGroups());
            $app['twig']->addGlobal('webface_fields_by_group', $groups);
        }

        return $builder;
    }

    public function addFieldToBuilder($field, FormBuilderInterface $builder, Application $app, $data = null)
    {
        $fieldName = $field['name'];
        if ($data && isset($data[$fieldName])) {
            $field['value'] = $data[$fieldName];
        }

        $config = isset($field['config']) ? $field['config'] : array();
        $config = array_merge(array('required' => true), $config);
        switch ($field['type']) {
            case 'text':
                $builder->add($fieldName, 'text', array('label' => $field['label'], 'required' => $config['required']));
                break;
            case 'textarea':
                $builder->add($fieldName, 'textarea', array('label' => $field['label'], 'required' => $config['required']));
                break;
            case 'html':
                $globals = $app['twig']->getGlobals();
                $htmls = isset($globals['webface_htmls']) ? $globals['webface_htmls'] : array();
                $htmls[] = $fieldName;
                $app['twig']->addGlobal('webface_htmls', $htmls);
                $app['twig']->addGlobal('webface_need_html', true);
                $builder->add($fieldName, 'tinymce_textarea', array('label' => $field['label'], 'required' => $config['required']));
                break;
            case 'slug':
                $globals = $app['twig']->getGlobals();
                $slugs = isset($globals['webface_slugs']) ? $globals['webface_slugs'] : array();
                $slugs[$fieldName] = $config['from_field'];
                $app['twig']->addGlobal('webface_slugs', $slugs);
                $app['twig']->addGlobal('webface_need_to_slug', true);

                // change type and add this field as usual
                $field['type'] = 'text';
                $this->addFieldToBuilder($field, $builder, $app, $data);
                break;
            case 'password':
                $builder->add($fieldName, 'password', array('label' => $field['label'], 'required' => $config['required']));
                break;
            case 'integer':
                $builder->add($fieldName, 'integer', array('label' => $field['label'], 'required' => $config['required']));
                break;
            case 'number':
                $builder->add($fieldName, 'number', array('label' => $field['label'], 'required' => $config['required']));
                break;
            case 'primary':
            case 'hidden':
                $builder->add($fieldName, 'hidden');
                break;
            case 'boolean':
                if (isset($field['value'])) {
                    $currentData = $builder->getData();
                    $currentData[$fieldName] = (bool) $field['value'];
                    $builder->setData($currentData);
                }
                $builder->add($fieldName, 'checkbox', array('label' => $field['label'], 'required' => $config['required']));
                break;
            case 'file':
                // add hidden field which then be overwritten by hardcoded select field
                $builder->add('_' . $fieldName . '_action', 'hidden');
                $builder->add($fieldName, 'file', array(
                    'label'    => $field['label'],
                    //'required'     => !empty($field['value']) ? false : $config['required'],
                    'required' => false,
                ));
                break;
            case 'image':
                // add hidden field which then be overwritten by hardcoded select field
                $builder->add('_' . $fieldName . '_action', 'hidden');
                $builder->add($fieldName, 'editable_image', array(
                    'label'        => $field['label'],
                    'required'     => !empty($field['value']) ? false : $config['required'],
                    'path'         => $app['webface.upload_url'] . '/' . $field['type'] . 's/' . $config['destination'] . '/',
                    'allow_delete' => !$config['required']
                ));
                break;
            case 'enum':
                $expanded = count($config['options']) <= 3;
                $builder->add($fieldName, 'choice', array(
                    'label'       => $field['label'],
                    'choices'     => $config['options'],
                    'expanded'    => $expanded,
                    'required'    => $config['required'],
                    'empty_value' => isset($config['empty_value'])
                                     ? $config['empty_value']
                                     : ($config['required'] ? false : ''),
                    'attr'        => array(
                        'class' => $expanded ? 'field-enum-expanded' : 'field-enum',
                    ),
                ));
                break;
            case 'relation':
                switch ($config['relation_type']) {
                    case 'belongs_to':
                        if (isset($config['relation_options_getter'])) {
                            $options = $this->$config['relation_options_getter']($app, $fieldName, $field);
                        } else {
                            $condition = isset($config['relation_condition']) ? "WHERE {$config['relation_condition']}" : '';
                            $query = "SELECT {$config['relation_field']}, {$config['relation_display']}
                            FROM {$config['relation_table']}"
                                . $condition;
                            $entities = $app['db']->fetchAll($query);
                            $options = array();
                            foreach ($entities as $entity) {
                                $options[$entity[$config['relation_field']]] = $entity[$config['relation_display']];
                            }
                        }

                        // подменяем поле, чтобы добавить
                        $field['type'] = 'enum';
                        $field['config'] = array(
                            'options'     => $options,
                            'required'    => $config['required'],
                        );
                        if (isset($config['empty_value'])) {
                            $field['config']['empty_value'] = $config['empty_value'];
                        }

                        $this->addFieldToBuilder($field, $builder, $app, $data);
                        break;

                    case 'has_many':
                        // @todo relation_condition?
                        $relationController = $config['relation_controller'];
                        if ($data && isset($data[$config['relation_field']])) {
                            $condition = " WHERE `{$config['relation_foreign_field']}` = {$data[$config['relation_field']]}";

                            $query = "SELECT " . implode(', ', array_merge(array('id'), $relationController->getFormFieldNames()))
                                . " FROM {$relationController->getTable()}"
                                . $condition;
                            $entities = $app['db']->fetchAll($query);

                            if (count($entities)) {
                                $currentData = $builder->getData();
                                if (!$currentData) {
                                    $currentData = array();
                                }

                                // dirty hack to prevent boolean exception
                                $relationController = $field['config']['relation_controller'];
                                $relationFields = $relationController->describeFields($relationController->getFormFieldNames());
                                foreach ($relationFields as $relationFieldName => $relationField) {
                                    if ($relationField['type'] === 'boolean') {
                                        foreach ($entities as &$entity) {
                                            if (isset($entity[$relationFieldName])) {
                                                $entity[$relationFieldName] = (bool) $entity[$relationFieldName];
                                            }
                                        }
                                    }
                                }

                                $builder->setData(array_merge($currentData, array($fieldName => $entities)));
                            }
                        }

                        $globals = $app['twig']->getGlobals();
                        $collections = isset($globals['webface_collections']) ? $globals['webface_collections'] : array();
                        $collections[] = $fieldName;
                        $app['twig']->addGlobal('webface_collections', $collections);
                        $app['twig']->addGlobal('webface_need_collections', true);

                        $formType = new EmbeddedHasManyFormType($app, $this, $fieldName, $field);
                        $builder->add($fieldName, 'collection', array(
                            'label' => $field['label'],
                            'attr' => array(
                                'class' => 'collection',
                            ),
                            'type' => $formType,
                            'allow_add' => true,
                            'allow_delete' => true,
                            'by_reference' => false,
                        ));
                        break;

                    case 'has_many_and_belongs_to':
                        if (isset($config['relation_options_getter']) && method_exists($this, $config['relation_options_getter'])) {
                            $options = $this->$config['relation_options_getter']($app, $fieldName, $field);
                        } else {
                            $condition = isset($config['relation_condition']) ? "WHERE {$config['relation_condition']}" : '';
                            $query = "SELECT {$config['relation_foreign_field']}, {$config['relation_foreign_display']}
                            FROM {$config['relation_table']}"
                                . $condition;
                            $entities = $app['db']->fetchAll($query);
                            $options = array();
                            foreach ($entities as $entity) {
                                $options[$entity[$config['relation_foreign_field']]] = $entity[$config['relation_foreign_display']];
                            }
                        }

                        if ($data && isset($data[$config['relation_field']])) {
                            if (isset($config['relation_options_setter'])) {
                                $fieldData = $this->$config['relation_options_setter']($app, $data, $fieldName, $field);
                            } else {
                                $query = "SELECT * FROM {$config['relation_map_table']}"
                                    . " WHERE `{$config['relation_map_field']}` = {$data[$config['relation_field']]}";
                                $entities = $app['db']->fetchAll($query);
                                $fieldData = array();
                                foreach ($entities as $entity) {
                                    $fieldData[] = $entity[$config['relation_map_foreign_field']];
                                }
                            }

                            $currentData = $builder->getData();
                            if (!$currentData) {
                                $currentData = array();
                            }

                            $builder->setData(array_merge($currentData, array($fieldName => $fieldData)));
                        }

                        $builder->add($fieldName, 'grouped_choice', array(
                            'label'    => $field['label'],
                            'choices'  => $options,
                            'expanded' => true,
                            'required' => $config['required'],
                            'multiple' => true,
                            'attr' => array(
                                'class' => 'grouped-choice',
                            ),
                        ));
                        break;
                }

                break;

            default:
                throw new FormException('Field "' . $fieldName . '" has unknown type "' . $field['type'] . '"');
                break;
        }
    }

    /**
     * @todo Переделать на событие формы preBind
     * @param $app
     * @param $form
     * @return mixed
     */
    public function prepareFormToStore($app, $form)
    {
        $data = $form->getData();
        $fields = $this->describeFields($this->getFormFieldNames());
        foreach ($data as $fieldName => $value) {
            if (!isset($fields[$fieldName])) {
                continue;
            }
            $fieldDefinition = $fields[$fieldName];
            switch ($fieldDefinition['type']) {
                case 'password':
                    $password = $app['security.encoder.digest']->encodePassword($value, null);
                    $data[$fieldName] = $password;
                    break;
                case 'image':
                case 'file':
                    if (isset($data['_' . $fieldName . '_action'])) {
                        $action = $data['_' . $fieldName . '_action'];
                    } else {
                        $action = 'update';
                    }
                    unset($data['_' . $fieldName . '_action']);

                    switch ($action) {
                        case 'stet':
                            unset($data[$fieldName]);
                            break;
                        case 'delete':
                            $data[$fieldName] = null;
                            break;
                        case 'update':
                        default:
                            $file = $form[$fieldName]->getData();
                            if (!$file instanceof UploadedFile) {
                                unset($data[$fieldName]);
                                continue;
                            }

                            $extension = $file->guessExtension();
                            if (!$extension) {
                                $extension = 'jpg';
                            }
                            $filename = $app['webface.admin']->generateRandomString() . '.' . $extension;
                            $file->move($app['webface.upload_dir'] . '/' . $fieldDefinition['type'] . 's/' . $fieldDefinition['config']['destination'], $filename);
                            $data[$fieldName] = $filename;
                            break;
                    }
                    break;
                case 'relation':
                    switch ($fieldDefinition['config']['relation_type']) {
                        case 'has_many':
                            unset($data[$fieldName]);
                            break;
                        case 'has_many_and_belongs_to':
                            unset($data[$fieldName]);
                            break;
                    }
                    break;
            }
        }

        return $data;
    }

    public function saveHasManyFields($hasManyFields, $id, $app, $form)
    {
        $data = $form->getData();
        foreach ($hasManyFields as $fieldName => $field) {
            $config = $field['config'];
            $relationController = $config['relation_controller'];
            $relationData = $data[$fieldName];
            $formType = new EmbeddedHasManyFormType($app, $this, $fieldName, $field, false);
            foreach ($relationData as $relationRowData) {
                $relationRowData[$config['relation_foreign_field']] = $id;
                $form = $app['form.factory']
                    ->createBuilder($formType, null, array('csrf_protection' => false))
                    ->getForm()
                    ->bind($relationRowData);
                if ($form->isValid()) {
                    $relationValidData = $relationController->prepareFormToStore($app, $form);
                    if (isset($relationValidData['id'])) {
                        $relationRowId = $relationValidData['id'];
                        unset($relationValidData['id']);
                        $app['db']->update($relationController->getTable(), $relationValidData, array('id' => $relationRowId));
                    } else {
                        $app['db']->insert($relationController->getTable(), $relationValidData);
                    }
                }
            }
        }
    }

    public function saveHasManyAndBelongsToFields($hasManyAndBelongsToFields, $id, $app, $form)
    {
        $data = $form->getData();
        foreach ($hasManyAndBelongsToFields as $fieldName => $field) {
            $config = $field['config'];

            if (isset($config['relation_options_saver'])) {
                $this->$config['relation_options_saver']($app, $id, $data, $fieldName, $field);

                continue;
            }

            // удаляем существующие записи
            $app['db']->delete($config['relation_map_table'], array($config['relation_map_field'] => $id));

            // вставляем новые
            $relationData = $data[$fieldName];
            foreach ($relationData as $relationRowData) {
                $app['db']->insert($config['relation_map_table'], array(
                    $config['relation_map_field'] => $id,
                    $config['relation_map_foreign_field'] => $relationRowData
                ));
            }
        }
    }

    public function saveRelationFields($id, $app, $form)
    {
        // ищем все has_many поля
        $formFields = $this->describeFields($this->getFormFieldNames());
        $hasManyFields = array();
        $hasManyAndBelongsToFields = array();
        foreach ($formFields as $fieldName => $field) {
            if ($field['type'] == 'relation' && $field['config']['relation_type'] == 'has_many') {
                $hasManyFields[$fieldName] = $field;
            }

            if ($field['type'] == 'relation' && $field['config']['relation_type'] == 'has_many_and_belongs_to') {
                $hasManyAndBelongsToFields[$fieldName] = $field;
            }
        }

        if (count($hasManyFields) > 0) {
            $this->saveHasManyFields($hasManyFields, $id, $app, $form);
        }

        if (count($hasManyAndBelongsToFields) > 0) {
            $this->saveHasManyAndBelongsToFields($hasManyAndBelongsToFields, $id, $app, $form);
        }
    }

    public function getFilter(Application $app)
    {
        $data = $app['request']->get('filter', array());
        $builder = $app['form.factory']->createNamedBuilder('filter', 'form', $data, array('csrf_protection' => false));

        $fieldNames = $this->getFilterFieldNames();
        $fields = $this->describeFields($fieldNames);
        foreach ($fields as &$field) {
            $field['config']['required'] = false;
        }

        return $this->addFieldsToBuilder($fields, $builder, $app, $data)->getForm();
    }

    public function getFilterCriteria(Application $app)
    {
        $filterCriteria = array();
        $filterParams = $app['request']->query->get('filter');
        $fieldNames = $this->getFilterFieldNames();
        foreach ($fieldNames as $fieldName) {
            if (!empty($filterParams[$fieldName])) {
                $filterCriteria[$fieldName] = $filterParams[$fieldName];
            }
        }

        return $filterCriteria;
    }

    public function buildFilterQuery(Application $app)
    {
        $filterQuery = array();
        $filterCriteria = $this->getFilterCriteria($app);
        foreach ($filterCriteria as $fieldName => $filter) {
            $filterQuery[] = is_numeric($filter)
                ? "`{$fieldName}` = {$filter}"
                : "`{$fieldName}` LIKE '%{$filter}%'";
        }

        return implode(' AND ', $filterQuery);
    }

    public function buildFilterGetQuery(Application $app)
    {
        $filterGetQuery = array();
        $filterCriteria = $this->getFilterCriteria($app);
        foreach ($filterCriteria as $fieldName => $filter) {
            $filterGetQuery[] = "filter[{$fieldName}]={$filter}";
        }

        return implode('&', $filterGetQuery);
    }

    public function getEntity(Application $app, $table, $id)
    {
        $query = "SELECT * FROM `{$table}` WHERE id = ?";

        return $app['db']->fetchAssoc($query, array($id));
    }

    public function actionList(Application $app, $page)
    {
        $fieldNames = $this->getListFieldNames();
        $fields = $this->describeFields($fieldNames);

        $filterWhereClause = $this->buildFilterQuery($app);
        $where = $filterWhereClause ? 'WHERE ' . $filterWhereClause : '';
        $orderBy = 'ORDER BY ' . $this->getListOrder();

        $queryCount = "SELECT COUNT(id) AS `count` FROM `{$this->table}` {$where};";
        $count = $app['db']->fetchAssoc($queryCount);
        $count = $count['count'];

        $perPage = $this->getListPerPageCount();
        $maxPage = max(ceil($count * 1.0 / $perPage * 1.0), 1);
        if ($page < 1) {
            $page = 1;
        }

        if ($page > $maxPage) {
            $page = $maxPage;
        }

        $offset = ($page - 1) * $perPage;

        $query = "SELECT * FROM `{$this->table}` {$where} {$orderBy} LIMIT {$offset}, {$perPage}";

        $entities = $app['db']->fetchAll($query);

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
                $this->addFieldsToBuilder($listEditFields, $builder, $app, $entity);
                $entities[$k]['list_edit_form'] = $builder->getForm()->createView();
            }
        }

        // prepare list_display fields
        foreach ($fields as $fieldName => $field) {
            if (isset($field['config']['list_display'])) {
                foreach ($entities as $k => $entity) {
                    $entities[$k][$fieldName] = $this->$field['config']['list_display']($app, $entity);
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
                        $entities[$k][$fieldName] = $relationEntity[$config['relation_display']];
                    }
                }
            }
        }

        $filter = $this->getFilter($app);
        if ($filter->count()) {
            $app['twig']->addGlobal('webface_need_list_filter', true);
        }

        // prepare entity actions
        foreach ($entities as $k => $entity) {
            $entities[$k]['actions'] = $this->getEntityActions($app, $entity);
        }

        return $app['twig']->render('list.twig', array(
            'table'      => $this->table,
            'fields'     => $fields,
            'filter'     => $filter->createView(),
            'entities'   => $entities,
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
        $form = $this->getForm($app);

        return $app['twig']->render('new.twig', array(
            'table' => $this->table,
            'form'  => $form->createView(),
            'ajax'  => $app['request']->isXmlHttpRequest(),
        ));
    }

    public function actionCreate(Application $app)
    {
        $form = $this->getForm($app);

        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            $data = $this->prepareFormToStore($app, $form);

            $app['db']->insert($this->table, $data);

            $this->saveRelationFields($app['db']->lastInsertId(), $app, $form);

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
        $entity = $this->getEntity($app, $this->table, $id);

        $form = $this->getForm($app, $entity);

        return $app['twig']->render('edit.twig', array(
            'table'  => $this->table,
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    public function actionUpdate(Application $app, $id)
    {
        $entity = $this->getEntity($app, $this->table, $id);

        $form = $this->getForm($app);

        $success = array(
            'type' => 'success',
            'text' => 'Запись успешно отредактирована!',
        );

        $error = array(
            'type' => 'error',
            'text' => 'Внимание, возникли какие-то ошибки!',
        );

        // Обработка AJAX-редактирования
        if ($app['request']->isXmlHttpRequest()) {
            $data = array_merge($entity, $app['request']->get('entity'));

            // OH GOD, symfony2 forms converts all values except NULL in true in checkboxes
            // so make them NULL
            foreach ($this->describeFields($this->getListFieldNames()) as $fieldName => $field) {
                if (isset($field['config']['list_edit']) && $field['config']['list_edit']
                        && $field['type'] == 'boolean') {
                    $data[$fieldName] = $data[$fieldName] ? $data[$fieldName] : null;
                }
            }

            $form->bind($data);
            if ($form->isValid()) {
                $app['db']->update($this->table, $this->prepareFormToStore($app, $form), array('id' => $id));
                $result = $success;
            } else{
                $result = $error;
            }

            return $app->json($result);
        }

        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            $data = $this->prepareFormToStore($app, $form);

            $app['db']->update($this->table, $data, array('id' => $id));

            $this->saveRelationFields($id, $app, $form);

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

    public function actionDelete(Application $app, $id)
    {
        $app['db']->delete($this->table, array('id' => $id));

        // @todo сообщение на удаление
        $app['session']->set($this->flashName, array(
            'type' => 'success',
            'text' => 'Запись успешно удалена!',
        ));

        return $app->redirect($app['url_generator']->generate($this->table . '_list'));
    }
}