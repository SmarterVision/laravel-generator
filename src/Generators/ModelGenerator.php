<?php

namespace InfyOm\Generator\Generators;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InfyOm\Generator\Common\CommandData;
use InfyOm\Generator\Common\GeneratorFieldRelation;
use InfyOm\Generator\Utils\FileUtil;
use InfyOm\Generator\Utils\TableFieldsGenerator;

class ModelGenerator extends BaseGenerator
{
    /**
     * Fields not included in the generator by default.
     *
     * @var array
     */
    protected $excluded_fields = [
        'created_at',
        'updated_at',
    ];

    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;
    private $fileName;
    private $table;

    /**
     * ModelGenerator constructor.
     *
     * @param \InfyOm\Generator\Common\CommandData $commandData
     */
    public function __construct(CommandData $commandData)
    {
        $this->commandData = $commandData;
        $this->path = $commandData->config->pathModel;
        $this->fileName = $this->commandData->modelName.'.php';
        $this->table = $this->commandData->dynamicVars['$TABLE_NAME$'];
    }

    public function generate()
    {
        $hasMedia = false;
        $relationshipAttributes = [];
        $relationshipAttributeTemplate = get_template('model.relationship_atribute', 'laravel-generator');

        foreach ($this->commandData->fields as $field) {
            if (!$field->inIndex) {
                continue;
            }
            if ($field->htmlType === 'file') {
                $hasMedia = true;
            }
            /*Multiple select generation*/
            if($field->dbInput === 'hidden,mtm'){

                $relationshipAttributes[] = fill_template([
                    '$RELATION_MODEL_PLURAL$' => $field->name,
                    '$RELATION_MODEL_PLURAL_CAPITAL$' => ucfirst($field->name),
                    '$RELATION_MODEL_TITLE$' => preg_split('/\./', $field->title)[1],
                ], $relationshipAttributeTemplate);

            }
        }

        if($hasMedia) {
            $templateData = get_template('model.upload_image_model', 'laravel-generator');
        }else{
            $templateData = get_template('model.model', 'laravel-generator');
        }
        $relationshipAttributes = implode('' . infy_nl_tab(1, 4), $relationshipAttributes);
        $templateData = str_replace('$RELATIONSHIP_ATTRIBUTE$', $relationshipAttributes, $templateData);
        $templateData = $this->fillTemplate($templateData);

        FileUtil::createFile($this->path, $this->fileName, $templateData);

        $this->commandData->commandComment("\nModel created: ");
        $this->commandData->commandInfo($this->fileName);
    }

    private function fillTemplate($templateData)
    {
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $templateData = $this->fillSoftDeletes($templateData);

        $fillables = [];
        $appends = [];

        foreach ($this->commandData->fields as $field) {
            if ($field->isFillable) {
                $fillables[] = "'".$field->name."'";
            }
            if($field->dbInput === 'hidden,mtm'){
                $appends[] = "'".$field->name."'";
            }
        }

        $templateData = $this->fillDocs($templateData);

        $templateData = $this->fillTimestamps($templateData);

        if ($this->commandData->getOption('primary')) {
            $primary = infy_tab()."protected \$primaryKey = '".$this->commandData->getOption('primary')."';\n";
        } else {
            $primary = '';
        }

        $templateData = str_replace('$PRIMARY$', $primary, $templateData);

        $templateData = str_replace('$FIELDS$', implode(','.infy_nl_tab(1, 2), $fillables), $templateData);
        $templateData = str_replace('$APPENDS$', implode(','.infy_nl_tab(1, 2), $appends), $templateData);

        $templateData = str_replace('$RULES$', implode(','.infy_nl_tab(1, 2), $this->generateRules()), $templateData);

        $templateData = str_replace('$CAST$', implode(','.infy_nl_tab(1, 2), $this->generateCasts()), $templateData);

        $templateData = str_replace(
            '$RELATIONS$',
            fill_template($this->commandData->dynamicVars, implode(PHP_EOL.infy_nl_tab(1, 1), $this->generateRelations())),
            $templateData
        );

        $templateData = str_replace('$GENERATE_DATE$', date('F j, Y, g:i a T'), $templateData);

        return $templateData;
    }

    private function fillSoftDeletes($templateData)
    {
        if (!$this->commandData->getOption('softDelete')) {
            $templateData = str_replace('$SOFT_DELETE_IMPORT$', '', $templateData);
            $templateData = str_replace('$SOFT_DELETE$', '', $templateData);
            $templateData = str_replace('$SOFT_DELETE_DATES$', '', $templateData);
        } else {
            $templateData = str_replace(
                '$SOFT_DELETE_IMPORT$', "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n",
                $templateData
            );
            $templateData = str_replace('$SOFT_DELETE$', infy_tab()."use SoftDeletes;\n", $templateData);
            $deletedAtTimestamp = config('infyom.laravel_generator.timestamps.deleted_at', 'deleted_at');
            $templateData = str_replace(
                '$SOFT_DELETE_DATES$', infy_nl_tab()."protected \$dates = ['".$deletedAtTimestamp."'];\n",
                $templateData
            );
        }

        return $templateData;
    }

    private function fillDocs($templateData)
    {
        if ($this->commandData->getAddOn('swagger')) {
            $templateData = $this->generateSwagger($templateData);
        } else {
            $docsTemplate = get_template('docs.model', 'laravel-generator');
            $docsTemplate = fill_template($this->commandData->dynamicVars, $docsTemplate);

            $fillables = '';
            foreach ($this->commandData->relations as $relation) {
                $fillables .= ' * @property '.$this->getPHPDocType($relation->type, $relation).PHP_EOL;
            }
            foreach ($this->commandData->fields as $field) {
                if ($field->isFillable) {
                    $fillables .= ' * @property '.$this->getPHPDocType($field->fieldType).' '.$field->name.PHP_EOL;
                }
            }
            $docsTemplate = str_replace('$GENERATE_DATE$', date('F j, Y, g:i a T'), $docsTemplate);
            $docsTemplate = str_replace('$PHPDOC$', $fillables, $docsTemplate);

            $templateData = str_replace('$DOCS$', $docsTemplate, $templateData);
        }

        return $templateData;
    }

    /**
     * @param $db_type
     * @param GeneratorFieldRelation|null $relation
     *
     * @return string
     */
    private function getPHPDocType($db_type, $relation = null)
    {
        switch ($db_type) {
            case 'datetime':
                return 'string|\Carbon\Carbon';
            case 'text':
                return 'string';
            case '1t1':
            case 'mt1':
                return '\\'.$this->commandData->config->nsModel.'\\'.$relation->inputs[0].' '.Str::camel($relation->inputs[0]);
            case '1tm':
                return '\Illuminate\Database\Eloquent\Collection'.' '.$relation->inputs[0];
            case 'mtm':
            case 'hmt':
                return '\Illuminate\Database\Eloquent\Collection'.' '.Str::camel($relation->inputs[0]);
            default:
                return $db_type;
        }
    }

    public function generateSwagger($templateData)
    {
        $fieldTypes = SwaggerGenerator::generateTypes($this->commandData->fields);

        $template = get_template('model_docs.model', 'swagger-generator');

        $template = fill_template($this->commandData->dynamicVars, $template);

        $template = str_replace('$REQUIRED_FIELDS$',
            '"'.implode('"'.', '.'"', $this->generateRequiredFields()).'"', $template);

        $propertyTemplate = get_template('model_docs.property', 'swagger-generator');

        $properties = SwaggerGenerator::preparePropertyFields($propertyTemplate, $fieldTypes);

        $template = str_replace('$PROPERTIES$', implode(",\n", $properties), $template);

        $templateData = str_replace('$DOCS$', $template, $templateData);

        return $templateData;
    }

    private function generateRequiredFields()
    {
        $requiredFields = [];

        foreach ($this->commandData->fields as $field) {
            if (!empty($field->validations)) {
                if (Str::contains($field->validations, 'required')) {
                    $requiredFields[] = $field->name;
                }
            }
        }

        return $requiredFields;
    }

    private function fillTimestamps($templateData)
    {
        $timestamps = TableFieldsGenerator::getTimestampFieldNames();

        $replace = '';

        if ($this->commandData->getOption('fromTable')) {
            if (empty($timestamps)) {
                $replace = infy_nl_tab()."public \$timestamps = false;\n";
            } else {
                list($created_at, $updated_at) = collect($timestamps)->map(function ($field) {
                    return !empty($field) ? "'$field'" : 'null';
                });

                $replace .= infy_nl_tab()."const CREATED_AT = $created_at;";
                $replace .= infy_nl_tab()."const UPDATED_AT = $updated_at;\n";
            }
        }

        return str_replace('$TIMESTAMPS$', $replace, $templateData);
    }

    private function generateRules()
    {
        $rules = [];

        foreach ($this->commandData->fields as $field) {
            if (!empty($field->validations)) {
                $rule = "'".$field->name."' => '".$field->validations."'";
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    public function generateCasts()
    {
        $casts = [];

        $timestamps = TableFieldsGenerator::getTimestampFieldNames();

        foreach ($this->commandData->fields as $field) {
            if (in_array($field->name, $timestamps)) {
                continue;
            }

            $rule = "'".$field->name."' => ";

            switch ($field->fieldType) {
                case 'integer':
                    $rule .= "'integer'";
                    break;
                case 'double':
                    $rule .= "'double'";
                    break;
                case 'float':
                    $rule .= "'float'";
                    break;
                case 'boolean':
                    $rule .= "'boolean'";
                    break;
                case 'dateTime':
                case 'dateTimeTz':
                    $rule .= "'datetime'";
                    break;
                case 'date':
                    $rule .= "'date'";
                    break;
                case 'enum':
                case 'string':
                case 'char':
                case 'text':
                    $rule .= "'string'";
                    break;
                default:
                    $rule = '';
                    break;
            }

            if (!empty($rule)) {
                $casts[] = $rule;
            }
        }

        return $casts;
    }

    private function generateRelations()
    {
        $relations = [];
Log::alert($this->commandData->relations);
        foreach ($this->commandData->relations as $relation) {
            $relationText = $relation->getRelationFunctionText();

            if (!empty($relationText)) {
                $relations[] = $relationText;
            }
        }

        return $relations;
    }

    public function rollback()
    {
        if ($this->rollbackFile($this->path, $this->fileName)) {
            $this->commandData->commandComment('Model file deleted: '.$this->fileName);
        }
    }
}
