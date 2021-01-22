<?php

namespace InfyOm\Generator\Generators\Scaffold;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InfyOm\Generator\Common\CommandData;
use InfyOm\Generator\Generators\BaseGenerator;
use InfyOm\Generator\Utils\FileUtil;
use InfyOm\Generator\Utils\GeneratorFieldsInputUtil;
use InfyOm\Generator\Utils\HTMLFieldGenerator;
use Themsaid\Langman\Manager;

class ViewGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;


    /** @var string */
    private $templateType;

    /** @var array */
    private $htmlFields;

    private $langManager;

    public function __construct(CommandData $commandData)
    {
        $this->commandData = $commandData;
        $this->path = $commandData->config->pathViews;
        $this->langManager = new Manager(new Filesystem(), config('langman.path'), []);
        $this->templateType = config('infyom.laravel_generator.templates', 'adminlte-templates');
    }

    public function generate()
    {
        if (!file_exists($this->path)) {
            mkdir($this->path, 0755, true);
        }

        $this->commandData->commandComment("\nGenerating Views...");

        if ($this->commandData->getOption('views')) {
            $viewsToBeGenerated = explode(',', $this->commandData->getOption('views'));

            if (in_array('index', $viewsToBeGenerated)) {
                $this->generateTable();
                $this->generateIndex();
            }

            if (count(array_intersect(['create', 'update'], $viewsToBeGenerated)) > 0) {
                $this->generateFields();
            }

            if (in_array('create', $viewsToBeGenerated)) {
                $this->generateCreate();
            }

            if (in_array('edit', $viewsToBeGenerated)) {
                $this->generateUpdate();
            }

            if (in_array('show', $viewsToBeGenerated)) {
                $this->generateShowFields();
                $this->generateShow();
            }
        } else {
            $this->generateTable();
            $this->generateIndex();
            $this->generateFields();
            $this->generateCreate();
            $this->generateUpdate();
            $this->generateShowFields();
            $this->generateShow();
            $this->generateLanguage();
        }

        $this->commandData->commandComment('Views created: ');
    }

    private function generateTable()
    {
        if ($this->commandData->getAddOn('datatables')) {
            $templateData = $this->generateDataTableBody();
            $this->generateDataTableActions();
        } else {
            $templateData = $this->generateBladeTableBody();
        }

        FileUtil::createFile($this->path, 'table.blade.php', $templateData);

        $this->commandData->commandInfo('table.blade.php created');
    }

    private function generateDataTableBody()
    {
        $templateData = get_template('scaffold.views.datatable_body', $this->templateType);

        return fill_template($this->commandData->dynamicVars, $templateData);
    }

    private function generateDataTableActions()
    {
        $templateData = get_template('scaffold.views.datatables_actions', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'datatables_actions.blade.php', $templateData);

        $this->commandData->commandInfo('datatables_actions.blade.php created');
    }

    private function generateBladeTableBody()
    {
        $templateData = get_template('scaffold.views.blade_table_body', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $templateData = str_replace('$FIELD_HEADERS$', $this->generateTableHeaderFields(), $templateData);

        $cellFieldTemplate = get_template('scaffold.views.table_cell', $this->templateType);

        $tableBodyFields = [];

        foreach ($this->commandData->fields as $field) {
            if (!$field->inIndex) {
                continue;
            }

            $tableBodyFields[] = fill_template_with_field_data(
                $this->commandData->dynamicVars,
                $this->commandData->fieldNamesMapping,
                $cellFieldTemplate,
                $field
            );
        }

        $tableBodyFields = implode(infy_nl_tab(1, 3), $tableBodyFields);

        return str_replace('$FIELD_BODY$', $tableBodyFields, $templateData);
    }

    private function generateTableHeaderFields()
    {
        $headerFieldTemplate = get_template('scaffold.views.table_header', $this->templateType);

        $headerFields = [];

        foreach ($this->commandData->fields as $field) {
            if (!$field->inIndex) {
                continue;
            }
            $headerFields[] = $fieldTemplate = fill_template_with_field_data(
                $this->commandData->dynamicVars,
                $this->commandData->fieldNamesMapping,
                $headerFieldTemplate,
                $field
            );
        }

        return implode(infy_nl_tab(1, 2), $headerFields);
    }

    private function generateIndex()
    {
        $templateData = get_template('scaffold.views.index', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        if ($this->commandData->getOption('datatables')) {
            $templateData = str_replace('$PAGINATE$', '', $templateData);
        } else {
            $paginate = $this->commandData->getOption('paginate');

            if ($paginate) {
                $paginateTemplate = get_template('scaffold.views.paginate', $this->templateType);

                $paginateTemplate = fill_template($this->commandData->dynamicVars, $paginateTemplate);

                $templateData = str_replace('$PAGINATE$', $paginateTemplate, $templateData);
            } else {
                $templateData = str_replace('$PAGINATE$', '', $templateData);
            }
        }

        FileUtil::createFile($this->path, 'index.blade.php', $templateData);

        $this->commandData->commandInfo('index.blade.php created');
    }

    /**
    '$MODEL_NAME_SNAKE$' => '$MODEL_NAME_HUMAN$',
    '$MODEL_NAME_SNAKE$_plural' => '$MODEL_NAME_PLURAL_HUMAN$',
    '$MODEL_NAME_SNAKE$_desc' => '$MODEL_NAME_PLURAL_HUMAN$ Management',
    '$MODEL_NAME_SNAKE$_edit' => 'Edit $MODEL_NAME_HUMAN$',
    '$MODEL_NAME_SNAKE$_table' => '$MODEL_NAME_PLURAL_HUMAN$ List',
    '$MODEL_NAME_SNAKE$_create' => 'Create $MODEL_NAME_HUMAN$',
     */

    /**
    '$MODEL_NAME_SNAKE$_$FIELD_NAME$' => '$FIELD_NAME_TITLE$',
    '$MODEL_NAME_SNAKE$_$FIELD_NAME$_help' => 'Insert $FIELD_NAME_TITLE$',
    '$MODEL_NAME_SNAKE$_$FIELD_NAME$_placeholder' => 'Insert $FIELD_NAME_TITLE$',
     */
    private function generateLanguage()
    {
        $this->langManager->fillKeys('lang', $this->getLangArray());
    }

    private function getTransArray($string = ''){
        $result = [];
        foreach ($this->langManager->languages() as $codelang){
            $result[$codelang] = $string;
        }
        return $result;
    }

    private function getLangArray(){
        $mSnake = $this->commandData->config->mSnake;
        $lang = [
            $mSnake => $this->getTransArray($this->commandData->config->mHuman),
            $mSnake."_plural" => $this->getTransArray($this->commandData->config->mHumanPlural),
            $mSnake."_desc" => $this->getTransArray($this->commandData->config->mHumanPlural.' Management'),
            $mSnake."_edit" => $this->getTransArray('Edit '.$this->commandData->config->mHuman),
            $mSnake."_table" => $this->getTransArray($this->commandData->config->mHumanPlural.' List'),
            $mSnake."_create" => $this->getTransArray('Create '.$this->commandData->config->mHuman),
        ];
        foreach ($this->commandData->fields as $field) {
            $title = preg_replace('/-|_/',' ',Str::title($field->name));
            if($field->inIndex || $field->inForm){
                $lang[$mSnake.'_'.$field->name] = $this->getTransArray($title);
            }
            if($field->inForm){
                $lang[$mSnake.'_'.$field->name.'_help'] = $this->getTransArray('Insert '.$title);
                $lang[$mSnake.'_'.$field->name.'_placeholder'] = $this->getTransArray('Insert '.$title);
            }
        }
        return $lang;
    }

    private function generateFields()
    {
        $this->htmlFields = [];
        $startSeparator = '<div class="d-flex flex-column col-sm-12 col-md-6">';
        $endSeparator = '</div>';

        foreach ($this->commandData->fields as $field) {
            if (!$field->inForm) {
                continue;
            }
            $this->commandData->addDynamicVariable('$RANDOM_VARIABLE$', 'var' . time() . rand() . 'ble');
            $fieldTemplate = HTMLFieldGenerator::generateHTML($field, $this->templateType);

            if (!empty($fieldTemplate)) {
                $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $fieldTemplate,
                    $field
                );
                $this->htmlFields[] = $fieldTemplate;
            }
        }

        foreach ($this->htmlFields as $index => $field) {
            if (round(count($this->htmlFields) / 2) == $index + 1) {
                $this->htmlFields[$index] = $this->htmlFields[$index] . "\n" . $endSeparator . "\n" . $startSeparator;
            }
        }

        $templateData = get_template('scaffold.views.fields', $this->templateType);
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $htmlFieldsString = implode("\n\n", $this->htmlFields);
        $htmlFieldsString = $startSeparator . "\n" . $htmlFieldsString . "\n" . $endSeparator;

        $templateData = str_replace('$FIELDS$', $htmlFieldsString, $templateData);
        FileUtil::createFile($this->path, 'fields.blade.php', $templateData);
        $this->commandData->commandInfo('field.blade.php created');
    }

    private function generateCreate()
    {
        $templateData = get_template('scaffold.views.create', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'create.blade.php', $templateData);
        $this->commandData->commandInfo('create.blade.php created');
    }

    private function generateUpdate()
    {
        $templateData = get_template('scaffold.views.edit', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'edit.blade.php', $templateData);
        $this->commandData->commandInfo('edit.blade.php created');
    }

    private function generateShowFields()
    {
        $fieldTemplate = get_template('scaffold.views.show_field', $this->templateType);

        $fieldsStr = '';

        foreach ($this->commandData->fields as $field) {
            $singleFieldStr = str_replace('$FIELD_NAME_TITLE$', Str::title(str_replace('_', ' ', $field->name)),
                $fieldTemplate);
            $singleFieldStr = str_replace('$FIELD_NAME$', $field->name, $singleFieldStr);
            $singleFieldStr = fill_template($this->commandData->dynamicVars, $singleFieldStr);

            $fieldsStr .= $singleFieldStr . "\n\n";
        }

        FileUtil::createFile($this->path, 'show_fields.blade.php', $fieldsStr);
        $this->commandData->commandInfo('show_fields.blade.php created');
    }

    private function generateShow()
    {
        $templateData = get_template('scaffold.views.show', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'show.blade.php', $templateData);
        $this->commandData->commandInfo('show.blade.php created');
    }

    public function rollback()
    {
        $files = [
            'table.blade.php',
            'index.blade.php',
            'fields.blade.php',
            'create.blade.php',
            'edit.blade.php',
            'show.blade.php',
            'show_fields.blade.php',
        ];

        if ($this->commandData->getAddOn('datatables')) {
            $files[] = 'datatables_actions.blade.php';
        }

        foreach ($files as $file) {
            if ($this->rollbackFile($this->path, $file)) {
                $this->commandData->commandComment($file . ' file deleted');
            }
        }
        $this->langManager->removeKey('lang', array_keys($this->getLangArray()));
    }

    public function generateCustomField($fields)
    {
        $this->htmlFields = [];
//        $this->commandData = new CommandData($this->commandData, CommandData::$COMMAND_TYPE_API_SCAFFOLD);
//        $this->commandData->commandType = CommandData::$COMMAND_TYPE_API_SCAFFOLD;
        $startSeparator = '<div class="d-flex flex-column col-sm-12 col-md-6">';
        $endSeparator = '</div>';

        foreach ($fields as $field) {
            $dynamicVars = [
                '$RANDOM_VARIABLE$' => 'var' . time() . rand() . 'ble',
                '$FIELD_NAME$' => $field->name,
                '$MODEL_NAME_SNAKE$' => $field->custom_field_model
            ];
            $field->htmlType = $field['type'];
            $fieldTemplate = HTMLFieldGenerator::generateHTML($field, config('infyom.laravel_generator.templates', 'adminlte-templates'));

            if (!empty($fieldTemplate)) {
                foreach ($dynamicVars as $variable => $value) {
                    $fieldTemplate = str_replace($variable, $value, $fieldTemplate);
                }
                $this->htmlFields[] = $fieldTemplate;
            }
        }

        foreach ($this->htmlFields as $index => $field) {
            if (round(count($this->htmlFields) / 2) == $index + 1) {
                $this->htmlFields[$index] = $this->htmlFields[$index] . "\n" . $endSeparator . "\n" . $startSeparator;
            }
        }

//        $templateData = get_template('scaffold.views.fields', $this->templateType);
//        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $htmlFieldsString = implode("\n\n", $this->htmlFields);
        $htmlFieldsString = $startSeparator . "\n" . $htmlFieldsString . "\n" . $endSeparator;

        return $htmlFieldsString;

//        $templateData = str_replace('$FIELDS$', $htmlFieldsString, $templateData);
//        FileUtil::createFile($this->path, 'fields.blade.php', $templateData);
//        $this->commandData->commandInfo('field.blade.php created');
    }
}
