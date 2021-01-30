<?php
use Illuminate\Support\Str;

if (!function_exists('infy_tab')) {
    /**
     * Generates tab with spaces.
     *
     * @param int $spaces
     *
     * @return string
     */
    function infy_tab($spaces = 4)
    {
        return str_repeat(' ', $spaces);
    }
}

if (!function_exists('infy_tabs')) {
    /**
     * Generates tab with spaces.
     *
     * @param int $tabs
     * @param int $spaces
     *
     * @return string
     */
    function infy_tabs($tabs, $spaces = 4)
    {
        return str_repeat(infy_tab($spaces), $tabs);
    }
}

if (!function_exists('infy_nl')) {
    /**
     * Generates new line char.
     *
     * @param int $count
     *
     * @return string
     */
    function infy_nl($count = 1)
    {
        return str_repeat(PHP_EOL, $count);
    }
}

if (!function_exists('infy_nls')) {
    /**
     * Generates new line char.
     *
     * @param int $count
     * @param int $nls
     *
     * @return string
     */
    function infy_nls($count, $nls = 1)
    {
        return str_repeat(infy_nl($nls), $count);
    }
}

if (!function_exists('infy_nl_tab')) {
    /**
     * Generates new line char.
     *
     * @param int $lns
     * @param int $tabs
     *
     * @return string
     */
    function infy_nl_tab($lns = 1, $tabs = 1)
    {
        return infy_nls($lns) . infy_tabs($tabs);
    }
}

if (!function_exists('get_template_file_path')) {
    /**
     * get path for template file.
     *
     * @param string $templateName
     * @param string $templateType
     *
     * @return string
     */
    function get_template_file_path($templateName, $templateType)
    {
        $templateName = str_replace('.', '/', $templateName);

        $templatesPath = config(
            'infyom.laravel_generator.path.templates_dir',
            base_path('resources/infyom/infyom-generator-templates/')
        );

        $path = $templatesPath . $templateName . '.stub';

        if (file_exists($path)) {
            return $path;
        }

        return base_path('vendor/infyomlabs/' . $templateType . '/templates/' . $templateName . '.stub');
    }
}

if (!function_exists('get_template')) {
    /**
     * get template contents.
     *
     * @param string $templateName
     * @param string $templateType
     *
     * @return string
     */
    function get_template($templateName, $templateType)
    {
        $path = get_template_file_path($templateName, $templateType);

        return file_get_contents($path);
    }
}

if (!function_exists('fill_template')) {
    /**
     * fill template with variable values.
     *
     * @param array $variables
     * @param string $template
     *
     * @return string
     */
    function fill_template($variables, $template)
    {
        foreach ($variables as $variable => $value) {
            $template = str_replace($variable, $value, $template);
        }

        return $template;
    }
}

if (!function_exists('fill_field_template')) {
    /**
     * fill field template with variable values.
     *
     * @param array $variables
     * @param string $template
     * @param \InfyOm\Generator\Common\GeneratorField $field
     *
     * @return string
     */
    function fill_field_template($variables, $template, $field)
    {
        foreach ($variables as $variable => $key) {
            $template = str_replace($variable, $field->$key, $template);
        }

        return $template;
    }
}

if (!function_exists('fill_template_with_field_data')) {
    /**
     * fill template with field data.
     *
     * @param array $variables
     * @param array $fieldVariables
     * @param string $template
     * @param \InfyOm\Generator\Common\GeneratorField $field
     *
     * @return string
     */
    function fill_template_with_field_data($variables, $fieldVariables, $template, $field)
    {

        if(!$field->isSearchable){
            $template = str_replace('$SEARCHABLE$',"'searchable'=>false,", $template);
        }else{
            $template = preg_replace('/\$SEARCHABLE\$/','',$template);
        }

        if( !$field->isOrderable){
            $template = str_replace('$ORDRERABLE$',"'orderable'=>false,", $template);
        }else{
            $template = preg_replace('/\$ORDRERABLE\$/','',$template);
        }

        if( !$field->isExportable){
            $template = str_replace('$EXPORTABLE$',"'exportable'=>false,", $template);
        }else{
            $template = preg_replace('/\$EXPORTABLE\$/','',$template);
        }

        if( !$field->isPrintable){
            $template = str_replace('$PRINTABLE$',"'printable'=>false,", $template);
        }else{
            $template = preg_replace('/\$PRINTABLE\$/','',$template);
        }

        $template = fill_template($variables, $template);

        return fill_field_template($fieldVariables, $template, $field);
    }
}

//if (!function_exists('fill_template_with_field_data')) {
//    /**
//     * fill template with field data.
//     *
//     * @param array $variables
//     * @param array $fieldVariables
//     * @param string $template
//     * @param \InfyOm\Generator\Common\GeneratorField $field
//     *
//     * @return string
//     */
//    function fill_template_with_field_data($variables, $fieldVariables, $template, $field)
//    {
//        $template = fill_template($variables, $template);
//
//        return fill_field_template($fieldVariables, $template, $field);
//    }
//}

if (!function_exists('model_name_from_table_name')) {
    /**
     * generates model name from table name.
     *
     * @param string $tableName
     *
     * @return string
     */
    function model_name_from_table_name($tableName)
    {
        return ucfirst(Str::camel(str_singular($tableName)));
    }
}
if (!function_exists('get_relation')) {

    /**
     * @param $field
     * @return mixed|string
     */
    function get_relation($field, $for = 'eloquent')
    {
        if($for === 'eloquent'){
            $relation = '->with("${MODEL}")';
        }elseif($for === 'view'){
            $relation = '->with("${MODEL}",$${MODEL})';
        }
        $model = Str::camel(preg_split('/\./', $field->title)[0]);
        $relation = str_replace('${MODEL}', $model, $relation);
        return $relation;
    }
}

if (!function_exists('get_send_data')) {

    /**
     * @param $variable
     * @param string $value
     * @return mixed|string
     */
    function get_send_data($variable, $value = '[]')
    {
        $data = '->with("${VARIABLE}",${VALUE})';
        $data = str_replace('${VARIABLE}', $variable, $data);
        $data = str_replace('${VALUE}', $value, $data);
        return $data;
    }
}

function fill_add_repositories_template($fieldNames, $templateData, $templateType = 'laravel-generator')
{

    $addUsedRepository = [];
    $addAttrRepository = [];
    $addAttrParamRepository = [];
    $addAttrInitRepository = [];

    foreach ($fieldNames as $field) {
        $modelName = preg_split('/\./', $field)[0];

        $addRepositoryTemplate = get_template('scaffold.controller.repository_attr', $templateType);

        $addRepositoryTemplate = fill_template([
            '$RELATION_MODEL$' => Str::studly($modelName),
            '$RELATION_MODEL_CAMEL$' => Str::camel($modelName),
            '$RELATION_MODEL_SNAKE$' => Str::snake($modelName)
        ], $addRepositoryTemplate);

        $addRepositoryTemplate = preg_split('/#{3}/', $addRepositoryTemplate);

        $addUsedRepository[] = trim($addRepositoryTemplate[0]);
        $addAttrRepository[] = trim($addRepositoryTemplate[1]);
        $addAttrParamRepository[] = trim($addRepositoryTemplate[2]);
        $addAttrInitRepository[] = trim($addRepositoryTemplate[3]);
    }


    $fields = implode(''.infy_nl_tab(1, 4), $addUsedRepository);
    $templateData = str_replace('$ADD_USED$', $fields, $templateData);

    $fields = implode('', $addAttrRepository);
    $templateData = str_replace('$ADD_ATTR$', $fields, $templateData);

    $fields = implode(''.infy_nl_tab(1, 4), $addAttrParamRepository);
    $templateData = str_replace('$ADD_ATTR_PARAM$', $fields, $templateData);

    $fields = implode(''.infy_nl_tab(1, 4), $addAttrInitRepository);
    $templateData = str_replace('$ADD_ATTR_INIT$', $fields, $templateData);

    return $templateData;

}
