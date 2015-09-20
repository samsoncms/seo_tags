<?php
/**
 * Created by PhpStorm.
 * User: molodyko
 * Date: 18.09.2015
 * Time: 16:13
 */

namespace samson\cms\seo;

use samson\cms\seo\schema\Facebook;
use samson\cms\seo\schema\Main;
use samson\cms\seo\schema\Twitter;

/**
 * Class Migrate for create structures in db
 * @package samson\cms\seo
 */
class Migrate
{

    /** @var \samson\activerecord\dbQuery */
    public $query = null;

    /** Type of structure with nested material */
    const NESTED_MATERIAL_TYPE_STRUCTURE = 1;

    const MAIN_PREFIX_NAME = 'main';

    public function __construct($query)
    {
        $this->query = $query;

        // Get all structures
        $this->structures = \samson\cms\seo\schema\Schema::getSchemas();
    }

    /**
     * Execute migrations
     * @throws \Exception
     */
    public function migrate()
    {
        // At first work with main structure
        $main = new Main();

        // Check if the main structure is exists then go out
        if ($this->isStructureExists($main->structureName, $main->structureUrl)) {
            return;

            // If main structure not exists then create it
        } else {

            // Create main structure
            $mainStructure = $this->createStructure($main->structureName, $main->structureUrl);

            // Create and bind all nested field
            //$this->buildFieldsToStructure($main->fields, $mainStructure->id, self::MAIN_PREFIX_NAME);

            // If nested material don't exist then create and assign it
            $this->buildNestedMaterial($mainStructure);

            // Iterate all nested structures and create each of all
            foreach ($this->structures as $schema) {

                // Create nested structure
                $structure = $this->createStructure(
                    $schema->structureName,
                    $schema->structureUrl,
                    self::NESTED_MATERIAL_TYPE_STRUCTURE,
                    $mainStructure->id
                );

                // Assign main fields to structure
                $this->buildFieldsToStructure($main->fields, $structure->id, $schema->id);

                // Assign fields to structure
                $this->buildFieldsToStructure($schema->fields, $structure->id, $schema->id);

                // If nested material don't exist then create and assign it
                $material = $this->buildNestedMaterial($structure);

                // If material was created then assign it to the main structure
                if ($material) {
                    $this->assignNestedMaterial($material, $mainStructure);
                }
            }
        }
    }


    /**
     * Get nested material in structure
     * @return null
     */
    public function getNestedMaterial($structure)
    {

        // Get nested material
        $material = null;
        $material = dbQuery('material')->cond('MaterialID', $structure->MaterialID)->first();

        return $material;
    }

    /**
     * Create and assign fields to the structure
     * @param $fields
     * @param $structureId
     * @param $prefix
     * @throws \Exception
     */
    public function buildFieldsToStructure($fields, $structureId, $prefix = '')
    {

        // Iterate and create all fields
        foreach ($fields as $field) {

            // Create and add field to structure
            $fieldInstance = $this->createField(
                $field['Name'].'_'.$prefix,
                $field['Description'],
                $field['Type']
            );

            // If field was created
            if ($fieldInstance) {

                // Add field to structure
                $this->assignFieldToStructure($structureId, $fieldInstance->FieldID);

            } else {
                throw new \Exception('Error when create field');
            }
        }
    }

    /**
     * Create field
     * @param $name
     * @param $description
     * @param $type
     * @return \samson\activerecord\field
     */
    public function createField($name, $description, $type)
    {
        // Save value of field
        $field = new \samson\activerecord\field(false);
        $field->Name = $name;
        $field->Description = $description;
        $field->Type = $type;
        $field->Active = 1;
        $field->save();

        return $field;
    }

    /**
     * Assign field to the structure
     * @param $structureId
     * @param $fieldId
     * @return \samson\activerecord\structurefield
     */
    public function assignFieldToStructure($structureId, $fieldId)
    {
        // Save value of field
        $structureField = new \samson\activerecord\structurefield(false);
        $structureField->StructureID = $structureId;
        $structureField->FieldID = $fieldId;
        $structureField->Active = 1;
        $structureField->save();

        return $structureField;
    }

    /**
     * Create structure and if isset parent structure assign it to them
     * @param $name
     * @param $url
     * @param int $type
     * @param int $parentId
     * @return \samson\activerecord\structure
     */
    public function createStructure($name, $url, $type = self::NESTED_MATERIAL_TYPE_STRUCTURE, $parentId = 0)
    {
        $structure = new \samson\activerecord\structure(false);
        $structure->Name = $name;
        $structure->Url = $url;
        $structure->Active = 1;
        $structure->type = $type;
        $structure->ParentID = $parentId;
        $structure->save();

        // Create structure relation if this structure have to be child
        if ($parentId != 0) {
            $structureRelation = new \samson\activerecord\structure_relation(false);
            $structureRelation->child_id = $structure->StructureID;
            $structureRelation->parent_id = $parentId;
            $structureRelation->save();
        }

        return $structure;
    }

    /**
     * Get structure if exists
     * @param $name
     * @param $url
     * @return mixed
     */
    public function isStructureExists($name, $url)
    {
        return $this->query->className('\samson\cms\Navigation')
            ->cond('Name', $name)
            ->cond('Url', $url)
            ->first();
    }

    /**
     * Assign nested material to the structure
     * @param $material
     * @param $structure
     */
    public function assignNestedMaterial($material, $structure)
    {
        // Assign material to structure
        $structureMaterial = new \samson\activerecord\structurematerial(false);
        $structureMaterial->MaterialID = $material->MaterialID;
        $structureMaterial->StructureID = $structure->StructureID;
        $structureMaterial->Active = 1;
        $structureMaterial->save();

        // Update structure field
        $structure->MaterialID = $material->MaterialID;
        $structure->save();
    }

    /**
     * Create nested material of structure
     * @param $name
     * @param $url
     * @return \samson\activerecord\material
     */
    public function createNestedMaterial($name, $url)
    {
        $material = new \samson\activerecord\material(false);
        $material->Name = $name;
        $material->Url = $url;
        $material->Active = 1;
        $material->save();

        return $material;
    }

    /**
     * Create nested material on the structure if it don't exists and assign it to the passed structure
     * @param $structure
     * @return null|\samson\activerecord\material
     */
    public function buildNestedMaterial($structure)
    {

        // Get nested material
        $material = $this->getNestedMaterial($structure);
        if (!$material) {

            // Set prefix of material
            $prefix = 'Material of ';
            $material = $this->createNestedMaterial(
                $prefix.$structure->Name,
                $prefix.$structure->Url
            );

            $this->assignNestedMaterial($material, $structure);

            return $material;
        }

        return null;
    }
}