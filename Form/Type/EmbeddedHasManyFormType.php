<?php

namespace WebFace\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class EmbeddedHasManyFormType extends AbstractType
{
    protected $app = null;
    protected $parentController = null;
    protected $relationName = null;
    protected $relationDefinition = null;
    protected $removeParentReference = false;


    public function __construct($app, $parentController, $relationName, $relationDefinition, $removeParentReference = true)
    {
        $this->app = $app;
        $this->parentController = $parentController;
        $this->relationName = $relationName;
        $this->relationDefinition = $relationDefinition;
        $this->removeParentReference = $removeParentReference;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $relationController = $this->relationDefinition['config']['relation_controller'];
        $fieldNames = $relationController->getFormFieldNames($this->app);

        if ($this->removeParentReference) {
            $relationForeignField = $this->relationDefinition['config']['relation_foreign_field'];

            if (($index = array_search($relationForeignField, $fieldNames)) !== false) {
                unset($fieldNames[$index]);
            }
        }

        $builder->add('id', 'hidden', array(
            'attr' => array(
                'data-delete-path' => $this->app['url_generator']->generate($relationController->getTable() . '_delete', array('id' => 0)),
            ),
        ));

        $fields = $relationController->describeFields($fieldNames);
        $relationController->addFieldsToBuilder($fields, $builder, $this->app);
    }

    public function getName()
    {
        return $this->relationName;
    }
}