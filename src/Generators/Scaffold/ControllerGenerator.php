<?php

namespace InfyOm\Generator\Generators\Scaffold;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InfyOm\Generator\Common\CommandData;
use InfyOm\Generator\Generators\BaseGenerator;
use InfyOm\Generator\Utils\FileUtil;

class ControllerGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;

    /** @var string */
    private $mSnake;

    /** @var string */
    private $templateType;

    /** @var string */
    private $fileName;

    public function __construct(CommandData $commandData)
    {
        $this->commandData = $commandData;
        $this->path = $commandData->config->pathController;
        $this->mSnake = $commandData->config->mSnake;
        $this->templateType = config('infyom.laravel_generator.templates', 'adminlte-templates');
        $this->fileName = $this->commandData->modelName . 'Controller.php';
    }

    public function generate()
    {
        $mediaFields = [];
        $selectFields = [];
        $selectedAddFields = [];
        $selectedEditFields = [];
        $selectsUpdateFields = [];
        $needRepositoy = [];
        $relations = '';
        if ($this->commandData->getAddOn('datatables')) {
            $templateData = get_template('scaffold.controller.datatable_controller', 'laravel-generator');
            $mediaFieldTemplate = get_template('scaffold.controller.upload_field', $this->templateType);
            $removeMediaTemplate = get_template('scaffold.controller.remove_media', $this->templateType);
            $selectFieldTemplate = get_template('scaffold.controller.select_relations', $this->templateType);

            $selectedAddFieldTemplate = get_template('scaffold.controller.selected_relations_add', $this->templateType);
            $selectedEditFieldTemplate = get_template('scaffold.controller.selected_relations_edit', $this->templateType);
            $selectsUpdateFieldTemplate = get_template('scaffold.controller.selects_relations_update', $this->templateType);

            foreach ($this->commandData->fields as $field) {
                if (!$field->inIndex) {
                    continue;
                }
                if ($field->htmlType === 'file') {
                    if(!in_array('upload', $needRepositoy)){
                        $needRepositoy[] = 'upload';
                    }
                    $mediaFields[] = $fieldTemplate = fill_template_with_field_data(
                        $this->commandData->dynamicVars,
                        $this->commandData->fieldNamesMapping,
                        $mediaFieldTemplate,
                        $field
                    );
                } elseif ($field->htmlType === 'select' && $field->title) {
                    if(!in_array($field->title, $needRepositoy)) {
                        $needRepositoy[] = $field->title;
                    }
                    $selectFields[] = fill_template([
                        '$RELATION_MODEL$' => preg_split('/\./', $field->title)[0],
                        '$RELATION_MODEL_CAMEL$' => Str::camel(preg_split('/\./', $field->title)[0]),
                        '$RELATION_MODEL_TITLE$' => preg_split('/\./', $field->title)[1],
                    ], $selectFieldTemplate);

                    $relations .= get_relation($field, 'view');

                    /*Multiple select generation*/
                    if($field->dbInput === 'hidden,mtm'){

                        $selectedAddFields[] = fill_template([
                            '$RELATION_MODEL_PLURAL$' => $field->name
                        ], $selectedAddFieldTemplate);

                        $selectedEditFields[] = fill_template([
                            '$RELATION_MODEL_PLURAL$' => $field->name
                        ], $selectedEditFieldTemplate);

                        $selectsUpdateFields[] = fill_template([
                            '$RELATION_MODEL_PLURAL$' => $field->name,
                        ], $selectsUpdateFieldTemplate);

                        $relations .= get_send_data($field->name.'Selected','$'.$field->name.'Selected');
                    }
                }
            }
            $this->generateDataTable();
        } else {
            $templateData = get_template('scaffold.controller.controller', 'laravel-generator');
            $paginate = $this->commandData->getOption('paginate');

            if ($paginate) {
                $templateData = str_replace('$RENDER_TYPE$', 'paginate(' . $paginate . ')', $templateData);
            } else {
                $templateData = str_replace('$RENDER_TYPE$', 'all()', $templateData);
            }
        }

        $templateData = str_replace('$REMOVE_MEDIA_FUNCTION$', $removeMediaTemplate, $templateData);


        $fields = implode('' . infy_nl_tab(1, 4), $mediaFields);
        $templateData = str_replace('$MEDIA_COLUMNS$', $fields, $templateData);

        $fields = implode('' . infy_nl_tab(1, 4), $selectFields);
        $selectedAddFields = implode('' . infy_nl_tab(1, 4), $selectedAddFields);
        $selectedEditFields = implode('' . infy_nl_tab(1, 4), $selectedEditFields);
        $selectsUpdateFields = implode('' . infy_nl_tab(1, 4), $selectsUpdateFields);

        $templateData = str_replace('$SELECT_RELATIONS$', $fields, $templateData);
        $templateData = str_replace('$SELECT_RELATIONS_PARAM$', $relations, $templateData);
        $templateData = str_replace('$SELECTED_RELATIONS_ADD$', $selectedAddFields, $templateData);
        $templateData = str_replace('$SELECTED_RELATIONS_EDIT$', $selectedEditFields, $templateData);
        $templateData = str_replace('$SELECTS_RELATIONS_UPDATE$', $selectsUpdateFields, $templateData);

        $templateData = fill_add_repositories_template($needRepositoy, $templateData, $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, $this->fileName, $templateData);

        $this->commandData->commandComment("\nController created: ");
        $this->commandData->commandInfo($this->fileName);
    }

    private function generateDataTable()
    {
        $templateData = get_template('scaffold.datatable', 'laravel-generator');

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $headerFieldTemplate = get_template('scaffold.views.datatable_column', $this->templateType);
        $mediaFieldTemplate = get_template('scaffold.views.media_column', $this->templateType);
        $dateFieldTemplate = get_template('scaffold.views.date_column', $this->templateType);
        $booleanFieldTemplate = get_template('scaffold.views.boolean_column', $this->templateType);
        $selectsFieldTemplate = get_template('scaffold.views.selects_column', $this->templateType);

        $headerFields = [];
        $mediaFields = [];
        $dateFields = [];
        $booleanFields = [];
        $selectsFields = [];
        $selectsFieldsStr = '';
        $rawColumn = '';
        $relations = '';

        foreach ($this->commandData->fields as $field) {

            if (!$field->inIndex) {
                continue;
            }
            if ($field->htmlType === 'file') {
                $mediaFields[] = $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $mediaFieldTemplate,
                    $field
                );
                $rawColumn = $rawColumn . ",'" . $field->name . "'";
            } else if ($field->dbInput === 'timestamp') {
                $dateFields[] = $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $dateFieldTemplate,
                    $field
                );
                $rawColumn = $rawColumn . ",'" . $field->name . "'";
            }else if( $field->htmlType === 'boolean'){
                $booleanFields[] = $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $booleanFieldTemplate,
                    $field
                );
                $rawColumn = $rawColumn . ",'" . $field->name . "'";
            }
            if ($field->htmlType === 'select') {
                /*Multiple select generation*/
                if($field->dbInput === 'hidden,mtm'){
                    $this->commandData->fieldNamesMapping['$FIELD_TITLE$'] = 'name';
                    $selectsFieldsStr .= fill_template([
                        '$RELATION_MODEL_PLURAL$' => $field->name,
                        '$RELATION_MODEL_CAMEL$' => Str::camel(preg_split('/\./', $field->title)[0]),
                        '$RELATION_MODEL_TITLE$' => preg_split('/\./', $field->title)[1],
                    ], $selectsFieldTemplate);

                    $selectsFields[] = $fieldTemplate = fill_template_with_field_data(
                        $this->commandData->dynamicVars,
                        $this->commandData->fieldNamesMapping,
                        $selectsFieldsStr,
                        $field
                    );

                    $rawColumn = $rawColumn . ",'" . $field->name . "'";
                }else{
                    $this->commandData->fieldNamesMapping['$FIELD_TITLE$'] = 'title';
                    $relations .= get_relation($field);
                }

                $headerFields[] = $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $headerFieldTemplate,
                    $field
                );

            } else {
                $this->commandData->fieldNamesMapping['$FIELD_TITLE$'] = 'name';
                $headerFields[] = $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $headerFieldTemplate,
                    $field
                );
            }

        }

        $path = $this->commandData->config->pathDataTables;

        $fileName = $this->commandData->modelName . 'DataTable.php';

        $fields = implode(',' . infy_nl_tab(1, 3), $headerFields);
        $templateData = str_replace('$DATATABLE_COLUMNS$', $fields, $templateData);

        $fields = implode('' . infy_nl_tab(1, 4), $mediaFields);
        $templateData = str_replace('$MEDIA_COLUMNS$', $fields, $templateData);
        $templateData = str_replace('$MEDIA_RAW_COLUMNS$', $rawColumn, $templateData);
        $templateData = str_replace('$RELATIONS$', $relations, $templateData);

        $fields = implode('' . infy_nl_tab(1, 4), $dateFields);
        $templateData = str_replace('$DATE_COLUMNS$', $fields, $templateData);

        $fields = implode('' . infy_nl_tab(1, 4), $booleanFields);
        $templateData = str_replace('$BOOLEAN_COLUMNS$', $fields, $templateData);

        $fields = implode('' . infy_nl_tab(1, 4), $selectsFields);
        $templateData = str_replace('$SELECTS_COLUMNS$', $fields, $templateData);

        FileUtil::createFile($path, $fileName, $templateData);

        $this->commandData->commandComment("\nDataTable created: ");
        $this->commandData->commandInfo($fileName);
    }

    public function rollback()
    {

        if ($this->rollbackFile($this->path, $this->fileName)) {
            $this->commandData->commandComment('Controller file deleted: ' . $this->fileName);
        }

        if ($this->commandData->getAddOn('datatables')) {
            if ($this->rollbackFile($this->commandData->config->pathDataTables, $this->commandData->modelName . 'DataTable.php')) {
                $this->commandData->commandComment('DataTable file deleted: ' . $this->fileName);
            }
        }
        $line = "/'" . $this->mSnake . ".*,\n/";
        $langContents = file_get_contents($this->commandData->config->pathLang);
        $langContents = preg_replace_callback($line, function ($matshes) {
            return '';
        }, $langContents);
        $langContents = preg_replace_callback("/(\n)\n\n+/", function ($matshes) {
            return "\n\n";
        }, $langContents);
        file_put_contents($this->commandData->config->pathLang, $langContents);
    }
}
