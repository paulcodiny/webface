<?php

namespace WebFace\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class EditableImageType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        if ($value = $form->getViewData()) {
            $value = $options['path'] . $value;
        }

        $name = $view->vars['name'];
        $actionName = str_replace($name, '_' . $name . '_action', $view->vars['full_name']);
        $actionId   = str_replace($name, '_' . $name . '_action', $view->vars['id']);

        $view->vars = array_merge($view->vars, array(
            'required'     => false,
            'action_id'    => $actionId,
            'action_name'  => $actionName,
            'value'        => $value,
            'allow_delete' => $options['allow_delete'],
        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'path' => '/',
            'allow_delete' => false,
            'data_class' => null,
        ));
    }

    public function getParent()
    {
        return 'file';
    }

    public function getName()
    {
        return 'editable_image';
    }
}