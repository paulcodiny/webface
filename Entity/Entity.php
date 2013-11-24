<?php

namespace WebFace\Entity;

use Silex\Application;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Form;
use WebFace\Definition;
use WebFace\Form\Type\EmbeddedHasManyFormType;

class Entity
{
    /** @var Application */
    protected $app;

    /** @var string */
    protected $table;

    /** @var array */
    protected $fieldsDefinition;

    public function __construct(Application $app, $table, $fieldsDefinition)
    {
        $this->app = $app;
        $this->table = $table;
        $this->fieldsDefinition = $fieldsDefinition;
    }

    public function saveHasManyFields($hasManyFields, $id, Form $form)
    {
        $data = $form->getData();
        foreach ($hasManyFields as $fieldName => $field) {
            if (!isset($data[$fieldName])) {
                // there is nothing to save (possible, field was removed)
                continue;
            }

            $config = &$field['config'];

            $relationDefinition = $this->_getRelationDefinition($fieldName, $config);

            if (!isset($config['relation_foreign_field'])) {
                $config['relation_foreign_field'] = $relationDefinition->getEntity()->getReferenceField($this->table);
            }

            $relationData = $data[$fieldName];
            $formType = new EmbeddedHasManyFormType($this->app, $fieldName, $config['relation_table'], $relationDefinition, $relationDefinition->getFormFieldsDefinition());
            foreach ($relationData as $relationRowData) {
                $relationRowData[$config['relation_foreign_field']] = $id;

                /** @var Form $form */
                $form = $this->app['form.factory']
                    ->createBuilder($formType, null, array('csrf_protection' => false))
                    ->getForm()
                    ->submit($relationRowData);

                if ($form->isValid()) {
                    $relationValidData = $relationDefinition->getFormBuilder()->prepareFormToStore($form);
                    if (isset($relationValidData['id'])) {
                        $relationRowId = $relationValidData['id'];
                        unset($relationValidData['id']);
                        $this->app['db']->update($config['relation_table'], $relationValidData, array('id' => $relationRowId));
                    } else {
                        $this->app['db']->insert($config['relation_table'], $relationValidData);
                    }
                }
            }
        }
    }

    public function saveHasManyAndBelongsToFields($hasManyAndBelongsToFields, $id, Form $form)
    {
        $data = $form->getData();
        foreach ($hasManyAndBelongsToFields as $fieldName => $field) {
            $config = $field['config'];

            if (isset($config['relation_options_saver'])
                    && is_callable(array($this, $config['relation_options_saver']))) {
                $this->$config['relation_options_saver']($id, $data, $fieldName, $field);

                continue;
            }

            if (!isset($data[$fieldName])) {
                continue;
            }

            // get all existent records
            $currentRelationEntities = $this->app['db']->fetchAll("SELECT * FROM `" . $config['relation_map_table'] . "`
                WHERE `" . $config['relation_map_field'] . "` = ?", array($id));
            $newRelationData = $data[$fieldName];


            // search for new data
            $dataToInsert = array();
            foreach ($newRelationData as $i => $relationRowData) {
                $existent = false;

                // existent - exists both in new and in current
                foreach ($currentRelationEntities as $j => $entity) {
                    if ($entity[$config['relation_map_field']] == $id
                        && $entity[$config['relation_map_foreign_field']] == $relationRowData) {
                        $existent = true;
                        unset($newRelationData[$i], $currentRelationEntities[$j]);
                        break;
                    }
                }

                // need to insert - exists in new but not in current
                if (!$existent) {
                    $dataToInsert[] = $relationRowData;
                }
            }

            // need to delete - exists in current but not in new
            if (count($currentRelationEntities)) {
                $oldDataIds = array();
                foreach ($currentRelationEntities as $entity) {
                    $oldDataIds[] = $entity[$config['relation_map_foreign_field']];
                }
                $deleteQuery = "DELETE FROM `" . $config['relation_map_table'] . "`
                    WHERE `". $config['relation_map_field'] . "` = ?
                    AND `" . $config['relation_map_foreign_field'] . "` IN (" . implode(', ', $oldDataIds) . ")";
                $this->app['db']->executeQuery($deleteQuery, array($id));
            }


            // insert new ones
            foreach ($dataToInsert as $relationRowData) {
                $this->app['db']->insert($config['relation_map_table'], array(
                    $config['relation_map_field'] => $id,
                    $config['relation_map_foreign_field'] => $relationRowData,
                ));
            }
        }
    }

    public function getHasManyFields()
    {
        $hasManyFields = array();
        foreach ($this->fieldsDefinition as $fieldName => $field) {
            if ($field['type'] == 'relation' && $field['config']['relation_type'] == 'has_many') {
                $hasManyFields[$fieldName] = $field;
            }
        }

        return $hasManyFields;
    }

    public function getBelongsToFields()
    {
        $belongsToFields = array();
        foreach ($this->fieldsDefinition as $fieldName => $field) {
            if ($field['type'] == 'relation' && $field['config']['relation_type'] == 'belongs_to') {
                $belongsToFields[$fieldName] = $field;
            }
        }

        return $belongsToFields;
    }

    public function getHasManyAndBelongsToFields()
    {
        $hasManyAndBelongsToFields = array();
        foreach ($this->fieldsDefinition as $fieldName => $field) {
            if ($field['type'] == 'relation' && $field['config']['relation_type'] == 'has_many_and_belongs_to') {
                $hasManyAndBelongsToFields[$fieldName] = $field;
            }
        }

        return $hasManyAndBelongsToFields;
    }

    public function saveRelationFields($id, Form $form)
    {
        $hasManyFields = $this->getHasManyFields();
        if (count($hasManyFields) > 0) {
            $this->saveHasManyFields($hasManyFields, $id, $form);
        }

        $hasManyAndBelongsToFields = $this->getHasManyAndBelongsToFields();
        if (count($hasManyAndBelongsToFields) > 0) {
            $this->saveHasManyAndBelongsToFields($hasManyAndBelongsToFields, $id, $form);
        }
    }

    public function deleteRelationEntities($entity)
    {
        $hasManyAndBelongsToFields = $this->getHasManyAndBelongsToFields();
        foreach ($hasManyAndBelongsToFields as $fieldName => $field) {
            if (isset($field['config']['relation_on_delete'])) {
                if ($field['config']['relation_on_delete'] == 'delete') {
                    $this->app['db']->delete(
                        $field['config']['relation_map_table'],
                        array(
                            $field['config']['relation_map_field'] => $entity[$field['config']['relation_field']]
                        )
                    );
                } elseif ($field['config']['relation_on_delete'] == 'set_null') {
                    $this->app['db']->update(
                        $field['config']['relation_map_table'],
                        array(
                            $field['config']['relation_map_field'] => null,
                        ),
                        array(
                            $field['config']['relation_map_field'] => $entity[$field['config']['relation_field']]
                        )
                    );
                }
            }
        }

        $hasMany = $this->getHasManyFields();
        foreach ($hasMany as $fieldName => $field) {
            $config = $field['config'];
            if (isset($config['relation_on_delete'])) {
                if ($config['relation_on_delete'] == 'delete') {
                    $relationDefinition = $this->_getRelationDefinition($fieldName, $config);

                    if (!isset($config['relation_foreign_field'])) {
                        $config['relation_foreign_field'] = $relationDefinition->getEntity()->getReferenceField($this->table);
                    }

                    $this->app['db']->delete($relationDefinition->getTable(), array(
                        $config['relation_foreign_field'] => $entity[$config['relation_field']]
                    ));
                } elseif ($config['relation_on_delete'] == 'set_null') {

                }
            }
        }
    }

    public function getReferenceField($table)
    {
        foreach ($this->fieldsDefinition as $fieldName => $field) {
            if ($field['type'] === 'relation'
                    && $field['config']['relation_type'] === 'belongs_to'
                    && $field['config']['relation_table'] === $table) {

                return $fieldName;
            }
        }

        throw new LogicException('There is no reference field for table "' . $table . '"');
    }

    /**
     * @param string $fieldName
     * @param array  $fieldConfig
     *
     * @return Definition
     */
    protected function _getRelationDefinition($fieldName, $fieldConfig)
    {
        return isset($fieldConfig['relation_definition'])
            ? $fieldConfig['relation_definition']
            : $this->app['webface.admin.definition.' . $fieldName];
    }

}