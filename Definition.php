<?php

namespace WebFace;

use Silex\Application;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use WebFace\Entity\Entity;
use WebFace\Entity\EntityManager;
use WebFace\Form\FormBuilder;
use WebFace\ListControl\FilterBuilder;
use WebFace\ListControl\ListBuilder;

class Definition
{
    /** @var Application */
    protected $app;

    /** @var ListBuilder */
    protected $listBuilder;

    /** @var FormBuilder */
    protected $formBuilder;

    /** @var FilterBuilder */
    protected $filterBuilder;

    /** @var Entity */
    protected $entity;

    /** @var EntityManager */
    protected $entityManager;

    protected $fieldsDefinition = array();

    public function __construct(Application $app, $table)
    {
        $this->app = $app;
        $this->table = $table;

        $this->setFieldsDefinition();
        $this->setFieldsDefinitionDefaults();
    }

    public function defineDependencies()
    {
        $this->entity        = new Entity($this->app, $this->table, $this->getFieldsDefinition());
        $this->formBuilder   = new FormBuilder($this->app, $this);

        $this->listBuilder   = new ListBuilder($this->app);
        $this->filterBuilder = new FilterBuilder($this->app, $this->getFilterFieldsDefinition(), $this->formBuilder);
        $this->entityManager = new EntityManager($this->app, $this->table);


    }

    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return ListBuilder
     */
    public function getListBuilder()
    {
        return $this->listBuilder;
    }

    /**
     * @return FormBuilder
     */
    public function getFormBuilder()
    {
        return $this->formBuilder;
    }

    /**
     * @return FilterBuilder
     */
    public function getFilterBuilder()
    {
        return $this->filterBuilder;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return Entity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    public function getCustomFieldsDefinition(array $fields)
    {
        $fieldsDefinition = array();
        foreach ($fields as $field) {
            if (!isset($this->fieldsDefinition[$field])) {
                throw new InvalidConfigurationException('Field "' . $field . '" does not exist in method getFields()');
            }

            $fieldsDefinition[$field] = $this->fieldsDefinition[$field];
        }

        return $fieldsDefinition;
    }

    public function getListFieldsDefinition()
    {
        return $this->getCustomFieldsDefinition($this->getListFields());
    }

    public function getFormFieldsDefinition($data = null)
    {
        return $this->getCustomFieldsDefinition($this->getFormFields($data));
    }

    public function getFilterFieldsDefinition()
    {
        return $this->getCustomFieldsDefinition($this->getFilterFields());
    }


    public function setFieldsDefinitionDefaults()
    {
        foreach ($this->fieldsDefinition as $fieldName => $fieldDefinition) {

            // setDefaults
            switch ($fieldDefinition['type']) {
                case 'relation':
                    $config = &$fieldDefinition['config'];

                    switch ($config['relation_type']) {
                        case 'belongs_to':
                            if (!isset($config['relation_field'])) {
                                $config['relation_field'] = 'id';
                            }

                            break;

                        case 'has_many':
                            if (!isset($config['relation_field'])) {
                                $config['relation_field'] = 'id';
                            }

                            if (!isset($config['relation_table'])) {
                                $config['relation_table'] = $fieldName;
                            }

                            break;
                    }
                    break;
            }


            $this->fieldsDefinition[$fieldName] = $fieldDefinition;
        }
    }


    public function setFieldsDefinition()
    {
        $this->fieldsDefinition = array();
    }

    public function getFieldsDefinition()
    {
        return $this->fieldsDefinition;
    }

    public function getListFields()
    {
        return array();
    }

    public function getListOrder()
    {
        return 'id DESC';
    }

    public function getListPerPageCount()
    {
        return 20;
    }

    public function getFormFields($data = null)
    {
        return array();
    }

    public function getFormGroups()
    {
        return array('default' => 'Основное');
    }

    public function getFilterFields()
    {
        return array();
    }
}