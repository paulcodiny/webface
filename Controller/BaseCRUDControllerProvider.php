<?php

namespace WebFace\Controller;

use Silex\ControllerCollection;
use Symfony\Component\Form\Form;
use WebFace\Form\Type\EmbeddedHasManyFormType;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\InvalidConfigurationException;

use Doctrine\Common\Util\Inflector;

use Silex\Application;
use Silex\ControllerProviderInterface;

use LogicException;


/**
 * Class BaseCRUDControllerProvider
 * @package WebFace\Controller
 */
class BaseCRUDControllerProvider implements ControllerProviderInterface
{

    const ENTITY_ID_TYPE_SCALAR = 'entity_id_type_scalar';
    const ENTITY_ID_TYPE_ASSOC  = 'entity_id_type_accos';
    const ENTITY_ID_TYPE_ARRAY  = 'entity_id_type_array';

    /**
     * Caches method getTable, only read
     * @var null|string
     */
    protected $table = null;
    protected $flashName = 'wf_flash';

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
     * @param Application $app
     *
     * @return array
     */
    public function getFields(Application $app)
    {
        return array();
    }

    /**
     * @param \Silex\Application $app
     *
     * @return array
     */
    public function getListFieldNames(Application $app)
    {
        return array();
    }

    /**
     * @param \Silex\Application $app
     * @param null               $data
     *
     * @return array
     */
    public function getFormFieldNames(Application $app, $data = null)
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

    public function getEntityActions(Application $app, $entity)
    {
        return array('_edit' => 'Редактировать', '_delete' => 'Удалить');
    }

    /**
     * Actions available in list page
     * @param Application $app
     * @return array
     */
    public function getListActions(Application $app)
    {
        return array('_add' => 'Добавить');
    }

    /**
     * Строка с тем, как сортировать данные в списке
     * String with order to list
     * @example id DESC, position ASC
     * @return string
     */
    public function getListOrder()
    {
        return 'id DESC';
    }

    /**
     * Number of entities displaying by page
     * @return int
     */
    public function getListPerPageCount()
    {
        return 20;
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

    public function describeFields(Application $app, array $fields)
    {
        $allFields = $this->getFields($app);
        $describedFields = array();
        foreach ($fields as $field) {
            if (!isset($allFields[$field])) {
                throw new InvalidConfigurationException('Field "' . $field . '" does not exist in method getFields()');
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

    /**
     * @param Application $app
     * @param             $groups
     *
     * @throws \LogicException
     */
    public function addGroupsViewData(Application $app, $groups)
    {
        $formGroups = $this->getFormGroups();
        $needGroups = array();
        foreach ($formGroups as $formGroupName => $formGroupDefinition) {
            if (is_array($formGroupDefinition)) {
                if (!isset($formGroupDefinition['label'])) {
                    throw new LogicException('Group "' . $formGroupName . '" must have label');
                }

                if (!isset($formGroupDefinition['role']) || $app['security']->isGranted($formGroupDefinition)) {
                    $needGroups[$formGroupName] = $formGroupDefinition;
                }
            } else {
                $needGroups[$formGroupName] = array('label' => $formGroupDefinition);
            }
        }

        $app['twig']->addGlobal('webface_need_groups', $needGroups);
        $app['twig']->addGlobal('webface_fields_by_group', $groups);
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

    /**
     * @param Application $app
     * @param null        $data
     *
     * @return Form
     */
    public function getForm(Application $app, $data = null)
    {
        $fieldNames = $this->getFormFieldNames($app, $data);
        $fields = $this->describeFields($app, $fieldNames);

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
    public function addFieldsToBuilder($fields, FormBuilderInterface $builder, Application $app, $data = null)
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
            $this->addGroupsViewData($app, $groups);
        }

        return $builder;
    }

    public function addFieldToBuilder($field, FormBuilderInterface $builder, Application $app, $data = null)
    {
        $fieldName = $field['name'];
        $fieldType = false;
        $fieldOptions = array();
        if ($data && isset($data[$fieldName])) {
            $field['value'] = $data[$fieldName];
        }

        if (!isset($field['config'])) {
            $field['config'] = array();
        }
        $config = &$field['config'];
        $config = array_merge(array('required' => true), $config);
        switch ($field['type']) {
            case 'text':
                $fieldType = 'text';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                );
                break;
            case 'textarea':
                $fieldType = 'textarea';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                    'attr'     => array(
                        'class' => 'input-xlarge',
                    ),
                );
                break;
            case 'html':
                $globals = $app['twig']->getGlobals();
                $htmls = isset($globals['webface_htmls']) ? $globals['webface_htmls'] : array();
                $htmls[] = $fieldName;
                $app['twig']->addGlobal('webface_htmls', $htmls);
                $app['twig']->addGlobal('webface_need_html', true);
                $fieldType = 'tinymce_textarea';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                );
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
                $fieldType = 'password';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                );
                break;
            case 'integer':
                $fieldType = 'integer';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                );
                break;
            case 'number':
                $fieldType = 'number';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                );
                break;
            case 'primary':
            case 'hidden':
                $fieldType = 'hidden';
                $fieldOptions = array();
                break;
            case 'boolean':
                if (isset($field['value'])) {
                    $currentData = $builder->getData();
                    $currentData[$fieldName] = (bool) $field['value'];
                    $builder->setData($currentData);
                }
                $fieldType = 'checkbox';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                );
                break;
            case 'file':
                // add hidden field which then be overwritten by hardcoded select field
                $builder->add('_' . $fieldName . '_action', 'hidden');
                $fieldType = 'file';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    //'required'     => !empty($field['value']) ? false : $config['required'],
                    'required' => false,
                );
                break;
            case 'image':
                // add hidden field which then be overwritten by hardcoded select field
                $builder->add('_' . $fieldName . '_action', 'hidden');
                $fieldType = 'editable_image';
                $fieldOptions = array(
                    'label'        => $field['label'],
                    'required'     => !empty($field['value']) ? false : $config['required'],
                    'path'         => $app['webface.upload_url'] . '/' . $field['type'] . 's/' . $config['destination'] . '/',
                    'allow_delete' => !$config['required']
                );
                break;
            case 'enum':
                $fieldType = 'choice';
                $fieldOptions = array(
                    'label'       => $field['label'],
                    'choices'     => $config['options'],
                    'expanded'    => false,
                    'required'    => $config['required'],
                    'empty_value' => isset($config['empty_value'])
                        ? $config['empty_value']
                        : ($config['required'] ? false : ''),
                    'attr'        => array(
                        'class' => 'field-enum',
                    ),
                );
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
                        /** @var BaseCRUDControllerProvider $relationController */
                        $relationController = $config['relation_controller'];
                        if (!isset($config['relation_field'])) {
                            $config['relation_field'] = 'id';
                        }
                        if (!isset($config['relation_foreign_field'])) {
                            $config['relation_foreign_field'] = $relationController->getReferenceField($app, $this->getTable());
                        }

                        // @todo relation_condition?
                        if ($data && isset($data[$config['relation_field']])) {
                            $condition = " WHERE `{$config['relation_foreign_field']}` = {$data[$config['relation_field']]}";

                            $query = "SELECT " . implode(', ', array_merge(array('id'), $relationController->getFormFieldNames($app)))
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
                                $relationFields = $relationController->describeFields($app, $relationController->getFormFieldNames($app, $entities));
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
                        $fieldType = 'collection';
                        $fieldOptions = array(
                            'label' => $field['label'],
                            'attr' => array(
                                'class' => 'collection',
                            ),
                            'type' => $formType,
                            'allow_add' => true,
                            'allow_delete' => true,
                            'by_reference' => false,
                        );
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

                        $fieldType = 'grouped_choice';
                        $fieldOptions = array(
                            'label'    => $field['label'],
                            'choices'  => $options,
                            'expanded' => true,
                            'required' => $config['required'],
                            'multiple' => true,
                            'attr' => array(
                                'class' => 'grouped-choice',
                            ),
                        );
                        break;
                }

                break;

            default:
                throw new InvalidConfigurationException('Field "' . $fieldName . '" has unknown type "' . $field['type'] . '"');
                break;
        }

        if (isset($config['field_type'])) {
            $fieldType = $config['field_type'];
        }

        if ($fieldType) {
            $builder->add($fieldName, $fieldType, $fieldOptions);
        }
    }

    /**
     * @todo Переделать на событие формы preBind
     * @param Application $app
     * @param Form        $form
     * @return mixed
     */
    public function prepareFormToStore(Application $app, Form $form)
    {
        $data = $form->getData();
        $fields = $this->describeFields($app, $this->getFormFieldNames($app, $data));
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

    public function saveHasManyFields($hasManyFields, $id, Application $app, Form $form)
    {
        $data = $form->getData();
        foreach ($hasManyFields as $fieldName => $field) {
            if (!isset($data[$fieldName])) {
                // there is nothing to save (possible, field was removed)
                continue;
            }

            $config = &$field['config'];
            /** @var BaseCRUDControllerProvider $relationController */
            $relationController = $config['relation_controller'];
            if (!isset($config['relation_field'])) {
                $config['relation_field'] = 'id';
            }
            if (!isset($config['relation_foreign_field'])) {
                $config['relation_foreign_field'] = $relationController->getReferenceField($app, $this->getTable());
            }
            $relationData = $data[$fieldName];
            $formType = new EmbeddedHasManyFormType($app, $this, $fieldName, $field, false);
            foreach ($relationData as $relationRowData) {
                $relationRowData[$config['relation_foreign_field']] = $id;
                /** @var Form $form */
                $form = $app['form.factory']
                    ->createBuilder($formType, null, array('csrf_protection' => false))
                    ->getForm()
                    ->submit($relationRowData);
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

    public function saveHasManyAndBelongsToFields($hasManyAndBelongsToFields, $id, $app, Form $form)
    {
        $data = $form->getData();
        foreach ($hasManyAndBelongsToFields as $fieldName => $field) {
            $config = $field['config'];

            if (isset($config['relation_options_saver'])) {
                $this->$config['relation_options_saver']($app, $id, $data, $fieldName, $field);

                continue;
            }

            if (!isset($data[$fieldName])) {
                continue;
            }

            // delete all existent records
            $app['db']->delete($config['relation_map_table'], array($config['relation_map_field'] => $id));

            // insert new ones
            $relationData = $data[$fieldName];
            foreach ($relationData as $relationRowData) {
                $app['db']->insert($config['relation_map_table'], array(
                    $config['relation_map_field'] => $id,
                    $config['relation_map_foreign_field'] => $relationRowData
                ));
            }
        }
    }

    public function saveRelationFields($id, Application $app, Form $form)
    {
        $formFields = $this->describeFields($app, $this->getFormFieldNames($app, $form->getData()));
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

    public function getReferenceField(Application $app, $table)
    {
        foreach ($this->getFields($app) as $fieldName => $field) {
            if ($field['type'] === 'relation'
                    && $field['config']['relation_type'] === 'belongs_to'
                    && $field['config']['relation_table'] === $table) {
                return $fieldName;
            }
        }
    }


    public function getFilter(Application $app)
    {
        $data = $app['request']->get('filter', array());
        $builder = $app['form.factory']->createNamedBuilder('filter', 'form', $data, array('csrf_protection' => false));

        $fieldNames = $this->getFilterFieldNames();
        $fields = $this->describeFields($app, $fieldNames);
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

    /**
     * @param Application $app
     * @param             $id
     *
     * @return mixed
     */
    public function getEntity(Application $app, $id)
    {
        $query = "SELECT * FROM `{$this->table}` WHERE id = ?";

        return $app['db']->fetchAssoc($query, array($id));
    }

    /**
     * @param array $entity
     * @param string $type ENTITY_ID_TYPE_* const
     *
     * @return array
     */
    public function getEntityId($entity, $type = self::ENTITY_ID_TYPE_ASSOC)
    {
        switch ($type) {
            case self::ENTITY_ID_TYPE_ASSOC:
                return array('id' => $entity['id']);

            case self::ENTITY_ID_TYPE_SCALAR:
                return $entity['id'];

            case self::ENTITY_ID_TYPE_ARRAY:
                return array($entity['id']);
        }
    }

    /**
     * @param Application $app
     * @param string      $where
     * @param string      $orderBy
     * @param string      $offset
     * @param string      $limit
     *
     * @return array
     */
    public function getEntitiesList(Application $app, $where, $orderBy, $offset, $limit)
    {
        $query = "SELECT * FROM `{$this->table}` {$where} {$orderBy} LIMIT {$offset}, {$limit}";

        $entities = $app['db']->fetchAll($query);

        return $entities;
    }

    public function actionList(Application $app, $page)
    {
        $fieldNames = $this->getListFieldNames($app);
        $fields = $this->describeFields($app, $fieldNames);

        $filterWhereClause = $this->buildFilterQuery($app);
        $where = $filterWhereClause ? 'WHERE ' . $filterWhereClause : '';
        $orderBy = 'ORDER BY ' . $this->getListOrder();

        $queryCount = "SELECT COUNT(*) AS `count` FROM `{$this->table}` {$where};";
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

        $entities = $this->getEntitiesList($app, $where, $orderBy, $offset, $perPage);

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
                        $entities[$k]['relation:belongs_to:display:' . $fieldName] = $relationEntity[$config['relation_display']];
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
            'actions'    => $this->getListActions($app),
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

        $form->handleRequest($app['request']);

        if ($form->isValid()) {
            $data = $this->prepareFormToStore($app, $form);

            $app['db']->insert($this->table, $data);
            $entityId = $app['db']->lastInsertId();

            $this->saveRelationFields($entityId, $app, $form);

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
        $entity = $this->getEntity($app, $id);

        $form = $this->getForm($app, $entity);

        return $app['twig']->render('edit.twig', array(
            'table'  => $this->table,
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    public function actionUpdate(Application $app, $id)
    {
        $entity = $this->getEntity($app, $id);

        $form = $this->getForm($app, $entity);

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
            $data = $this->prepareFormToStore($app, $form);
            $app['db']->update($this->table, $data, $this->getEntityId($entity));
            $this->saveRelationFields($id, $app, $form);
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
        foreach ($this->describeFields($app, $this->getListFieldNames($app)) as $fieldName => $field) {
            if (isset($field['config']['list_edit']) && $field['config']['list_edit']
                && $field['type'] == 'boolean') {
                $data[$fieldName] = $data[$fieldName] ? $data[$fieldName] : null;
            }
        }

        $form->submit($data);
        if ($form->isValid()) {
            $app['db']->update($this->table, $this->prepareFormToStore($app, $form), array('id' => $id));
            $this->afterUpdated($app, $entity, $data);
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }

    public function actionDelete(Application $app, $id)
    {
        $entity = $this->getEntity($app, $id);
        $app['db']->delete($this->table, $this->getEntityId($entity));
        $this->afterDeleted($app, $entity);

        // @todo Flashes about deleting are not displayed, fix it
        $app['session']->set($this->flashName, array(
            'type' => 'success',
            'text' => 'Запись успешно удалена!',
        ));

        return $app->redirect($app['url_generator']->generate($this->table . '_list'));
    }
}