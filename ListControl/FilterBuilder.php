<?php

namespace WebFace\ListControl;

use DateTime;
use \Silex\Application;
use Symfony\Component\Form\Form;
use WebFace\CurrentServiceContainer;
use WebFace\Form\FormBuilder;

class FilterBuilder
{
    /** @var Application */
    protected $app;

    /** @var array */
    protected $filterFieldsDefinition = array();

    /** @var FormBuilder */
    protected $formBuilder;

    public function __construct(Application $app, $filterFieldsDefinition, FormBuilder $formBuilder)
    {
        $this->app = $app;
        $this->filterFieldsDefinition = $filterFieldsDefinition;
        $this->formBuilder = $formBuilder;
    }

    /**
     * @return Form
     */
    public function getForm()
    {
        $data = $this->app['request']->get('filter', array());
        $builder = $this->app['form.factory']->createNamedBuilder('filter', 'form', $data, array('csrf_protection' => false));

        foreach ($this->filterFieldsDefinition as &$field) {
            $field['config']['required'] = false;
        }

        return $this->formBuilder
            ->addFieldsToBuilder($this->filterFieldsDefinition, $builder, $data)
            ->getForm();
    }

    public function getFilterCriteria()
    {
        $filterCriteria = array();
        $filterParams = $this->app['request']->query->get('filter');
        foreach ($this->filterFieldsDefinition as $fieldName => $field) {
            if (!empty($filterParams[$fieldName])) {
                $filterCriteria[$fieldName] = $filterParams[$fieldName];
            }
        }

        return $filterCriteria;
    }

    public function buildFilterQuery()
    {
        $filterQuery = array();
        $filterCriteria = $this->getFilterCriteria();
        foreach ($filterCriteria as $fieldName => $filter) {
            switch ($this->filterFieldsDefinition[$fieldName]['type']) {
                case 'date':
                    $dateArray = explode('/', $filter);
                    $date = implode('-', array_reverse($dateArray));
                    $filterQuery[] = "`{$fieldName}` = '{$date}'";
                    break;

                default:
                    $filterQuery[] = is_numeric($filter)
                        ? "`{$fieldName}` = {$filter}"
                        : "`{$fieldName}` LIKE '%{$filter}%'";
                    break;
            }

        }

        return implode(' AND ', $filterQuery);
    }

    public function buildFilterGetQuery()
    {
        $filterGetQuery = array();
        $filterCriteria = $this->getFilterCriteria();
        foreach ($filterCriteria as $fieldName => $filter) {
            $filterGetQuery[] = "filter[{$fieldName}]={$filter}";
        }

        return implode('&', $filterGetQuery);
    }
}