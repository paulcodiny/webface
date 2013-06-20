<?php

namespace WebFace\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class EditableImageType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormViewInterface $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        if ($value = $form->getViewData()) {
            $value = $options['path'] . $value;
        }

        $name = $view->getVar('name');
        $actionName = str_replace($name, '_' . $name . '_action', $view->getVar('full_name'));
        $actionId   = str_replace($name, '_' . $name . '_action', $view->getVar('id'));

        $view->addVars(array(
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