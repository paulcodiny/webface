<?php

namespace WebFace\Form;

use LogicException;
use Silex\Application;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use WebFace\Definition;
use WebFace\Form\Type\EmbeddedHasManyFormType;

class FormBuilder
{
    /** @var Application */
    protected $app;

    /** @var Definition */
    protected $definition;

    public function __construct(Application $app, Definition $definition)
    {
        $this->app        = $app;
        $this->definition = $definition;
    }

    public function setDataDefaultsFromFieldsDefinition($fields, $data)
    {
        foreach ($fields as $fieldName => $field) {
            if (isset($field['config']['default']) && !isset($data[$fieldName])) {
                if (is_callable($field['config']['default'])) {
                    $data[$fieldName] = $field['config']['default']($this->app);
                } else {
                    $data[$fieldName] = $field['config']['default'];
                }
            }
        }

        return $data;
    }

    /**
     * @param $fields
     *
     * @return array
     */
    public function scatterFieldsToGroups($fields)
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

        return $groups;
    }
    
    /**
     * @param  $groups
     *
     * @throws \LogicException
     */
    public function addGroupsViewData($groups)
    {
        $formGroups = $this->definition->getFormGroups();
        $needGroups = array();
        foreach ($formGroups as $formGroupName => $formGroupDefinition) {
            if (is_array($formGroupDefinition)) {
                if (!isset($formGroupDefinition['label'])) {
                    throw new LogicException('Group "' . $formGroupName . '" must have label');
                }

                if (!isset($formGroupDefinition['role']) || $this->app['security']->isGranted($formGroupDefinition)) {
                    $needGroups[$formGroupName] = $formGroupDefinition;
                }
            } else {
                $needGroups[$formGroupName] = array('label' => $formGroupDefinition);
            }
        }

        $this->app['twig']->addGlobal('webface_need_groups', $needGroups);
        $this->app['twig']->addGlobal('webface_fields_by_group', $groups);
    }

    /**
     * @param array [$data=null]
     *
     * @return Form
     */
    public function getForm($data = null)
    {
        $fieldsDefinition = $this->definition->getFormFieldsDefinition($data);
        $data = $this->setDataDefaultsFromFieldsDefinition($fieldsDefinition, $data);

        $builder = $this->app['form.factory']->createNamedBuilder('entity', 'form', $data, array('csrf_protection' => false));

        return $this->addFieldsToBuilder($fieldsDefinition, $builder, $data)->getForm();
    }

    /**
     * @param array                $fields
     * @param FormBuilderInterface $builder
     * @param array                [$data=null]
     *
     * @return FormBuilderInterface
     */
    public function addFieldsToBuilder($fields, FormBuilderInterface $builder, $data = null)
    {
        $groups = $this->scatterFieldsToGroups($fields);

        foreach ($fields as $fieldName => $field) {
            $field['name'] = $fieldName;
            $this->addFieldToBuilder($field, $builder, $data);
        }

        // if there is one group - push all fields to the root
        if (count($groups) > 1) {
            $this->addGroupsViewData($groups);
        }

        return $builder;
    }

    public function addFieldToBuilder($field, FormBuilderInterface $builder, $data = null)
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
                $globals = $this->app['twig']->getGlobals();
                $htmls = isset($globals['webface_htmls']) ? $globals['webface_htmls'] : array();
                $htmls[] = $fieldName;
                $this->app['twig']->addGlobal('webface_htmls', $htmls);
                $this->app['twig']->addGlobal('webface_need_html', true);
                $fieldType = 'tinymce_textarea';
                $fieldOptions = array(
                    'label'    => $field['label'],
                    'required' => $config['required'],
                );
                break;
            case 'date':
                $fieldType = 'text';
                $fieldOptions = array(
                    'attr' => array(
                        'class' => 'datepicker',
                    ),
                );
                break;
            case 'slug':
                $globals = $this->app['twig']->getGlobals();
                $slugs = isset($globals['webface_slugs']) ? $globals['webface_slugs'] : array();
                $slugs[$fieldName] = $config['from_field'];
                $this->app['twig']->addGlobal('webface_slugs', $slugs);
                $this->app['twig']->addGlobal('webface_need_to_slug', true);

                // change type and add this field as usual
                $field['type'] = 'text';
                $this->addFieldToBuilder($field, $builder, $this->app, $data);
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
                    'data_class' => null,
                );
                break;
            case 'image':
                // add hidden field which then be overwritten by hardcoded select field
                $builder->add('_' . $fieldName . '_action', 'hidden');
                $fieldType = 'editable_image';
                $fieldOptions = array(
                    'label'        => $field['label'],
                    'required'     => !empty($field['value']) ? false : $config['required'],
                    'path'         => $this->app['webface.upload_url'] . '/' . $field['type'] . 's/' . $config['destination'] . '/',
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
                        if (isset($config['relation_options_getter'])
                                && is_callable(array($this, $config['relation_options_getter']))) {
                            $options = $this->$config['relation_options_getter']($fieldName, $field);
                        } else {
                            $condition = isset($config['relation_condition']) ? "WHERE {$config['relation_condition']}" : '';
                            $query = "SELECT {$config['relation_field']}, {$config['relation_display']}
                            FROM {$config['relation_table']}"
                                . $condition;
                            $entities = $this->app['db']->fetchAll($query);
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

                        $this->addFieldToBuilder($field, $builder, $this->app, $data);
                        break;

                    case 'has_many':
                        if (!isset($config['relation_field'])) {
                            $config['relation_field'] = 'id';
                        }

                        if (!isset($config['relation_table'])) {
                            $config['relation_table'] = $fieldName;
                        }

                        /** @var Definition $relationDefinition */
                        $relationDefinition = $this->app['webface.admin.definition.' . $config['relation_table']];
                        $relationFormFieldNames = $relationDefinition->getFormFields();

                        if (!isset($config['relation_foreign_field'])) {
                            $config['relation_foreign_field'] = $relationDefinition->getEntity()->getReferenceField(
                                $this->definition->getEntityManager()->getTable()
                            );
                        }

                        // @todo relation_condition?
                        if ($data && isset($data[$config['relation_field']])) {
                            $condition = " WHERE `{$config['relation_foreign_field']}` = {$data[$config['relation_field']]}";
                            $selectFields = implode(', ', array_merge(array('id'), $relationFormFieldNames));
                            $query = "SELECT " . $selectFields
                                . " FROM {$config['relation_table']}"
                                . $condition;
                            $entities = $this->app['db']->fetchAll($query);

                            if (count($entities)) {
                                $currentData = $builder->getData();
                                if (!$currentData) {
                                    $currentData = array();
                                }

                                // dirty hack to prevent boolean exception
                                $relationFields = $relationDefinition->getFormFieldsDefinition();
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

                        $globals = $this->app['twig']->getGlobals();
                        $collections = isset($globals['webface_collections']) ? $globals['webface_collections'] : array();
                        $collections[] = $fieldName;
                        $this->app['twig']->addGlobal('webface_collections', $collections);
                        $this->app['twig']->addGlobal('webface_need_collections', true);

                        // From fields of the embedded form remove parent reference
                        if (($index = array_search($config['relation_foreign_field'], $relationFormFieldNames)) !== false) {
                            unset($relationFormFieldNames[$index]);
                        }

                        $formType = new EmbeddedHasManyFormType($this->app, $fieldName, $config['relation_table'], $relationDefinition, $relationDefinition->getCustomFieldsDefinition($relationFormFieldNames));
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
                            $options = $this->$config['relation_options_getter']($this->app, $fieldName, $field);
                        } else {
                            $condition = isset($config['relation_condition']) ? "WHERE {$config['relation_condition']}" : '';
                            $query = "SELECT {$config['relation_foreign_field']}, {$config['relation_foreign_display']}
                            FROM {$config['relation_table']}"
                                . $condition;
                            $entities = $this->app['db']->fetchAll($query);
                            $options = array();
                            foreach ($entities as $entity) {
                                $options[$entity[$config['relation_foreign_field']]] = $entity[$config['relation_foreign_display']];
                            }
                        }

                        if ($data && isset($data[$config['relation_field']])) {
                            if (isset($config['relation_options_setter'])
                                    && is_callable(array($this, $config['relation_options_setter']))) {
                                $fieldData = $this->$config['relation_options_setter']($data, $fieldName, $field);
                            } else {
                                $query = "SELECT * FROM {$config['relation_map_table']}"
                                    . " WHERE `{$config['relation_map_field']}` = {$data[$config['relation_field']]}";
                                $entities = $this->app['db']->fetchAll($query);
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
     * @param Form        $form
     * @return mixed
     */
    public function prepareFormToStore(Form $form)
    {
        $data = $form->getData();
        $fieldsDefinition = $this->definition->getFormFieldsDefinition($data);
        foreach ($data as $fieldName => $value) {
            if (!isset($fieldsDefinition[$fieldName])) {
                continue;
            }
            $fieldDefinition = $fieldsDefinition[$fieldName];
            switch ($fieldDefinition['type']) {
                case 'password':
                    $password = $this->app['security.encoder.digest']->encodePassword($value, null);
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
                            $filename = $this->app['webface.admin']->generateRandomString() . '.' . $extension;
                            $file->move($this->app['webface.upload_dir'] . '/' . $fieldDefinition['type'] . 's/' . $fieldDefinition['config']['destination'], $filename);
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
}