<?php

namespace WebFace\Form\Type;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class GroupedChoiceType extends ChoiceType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['has_groups'] = false;
        foreach ($options['choice_list']->getRemainingViews() as $index => $choice) {
            $view->vars['has_groups'] = is_array($choice);

            break;
        }

        parent::buildView($view, $form, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'grouped_choice';
    }
}