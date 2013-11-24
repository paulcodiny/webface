<?php

namespace WebFace\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use WebFace\Definition;
use WebFace\Form\FormBuilder;

class EmbeddedHasManyFormType extends AbstractType
{
    protected $app = null;
    protected $parentController = null;
    protected $relationName = null;

    /** @var \WebFace\Definition  */
    protected $relationDefinition = null;


    public function __construct($app, $relationName, $relationTable, Definition $relationDefinition, $fieldsDefinition)
    {
        $this->app                = $app;
        $this->relationName       = $relationName;
        $this->relationTable      = $relationTable;
        $this->relationDefinition = $relationDefinition;
        $this->fieldsDefinition   = $fieldsDefinition;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('id', 'hidden', array(
            'attr' => array(
                'data-delete-path' => $this->app['url_generator']->generate($this->relationTable . '_delete', array('id' => 0)),
            ),
        ));

        $this->relationDefinition->getFormBuilder()->addFieldsToBuilder($this->fieldsDefinition, $builder);
    }

    public function getName()
    {
        return $this->relationName;
    }
}