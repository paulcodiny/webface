<?php

namespace WebFace\Form\Type;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class GroupedChoiceType extends ChoiceType
{
    public function buildView(FormViewInterface $view, FormInterface $form, array $options)
    {
        foreach ($options['choice_list']->getRemainingViews() as $index => $choice) {
            $view->addVars(array('has_groups' => is_array($choice)));

            break;
        }

        parent::buildView($view, $form, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'field';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'grouped_choice';
    }
}