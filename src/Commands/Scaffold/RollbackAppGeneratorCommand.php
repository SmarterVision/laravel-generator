<?php

namespace InfyOm\Generator\Commands\Scaffold;

use InfyOm\Generator\Commands\BaseCommand;
use InfyOm\Generator\Common\CommandData;

class RollbackAppGeneratorCommand extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'infyom:app.rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback a full Application';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        // $this->commandData = new CommandData($this, CommandData::$COMMAND_TYPE_SCAFFOLD);
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        //parent::handle();
        //$this->call('serve');
//        $this->call('infyom:scaffold', ['model'=>'OptionDetail', '--fieldsFile' => base_path('schema/option_details.json'),'--skip'=>'*', '--migrate'=>'true']);
        
        $schema = config('app_generator.schema', []);
        $schema = array_reverse($schema);
        $array_keys = array_keys($schema);
        $last_key = end($array_keys);
        foreach ($schema as $key=>$table) {
            $arguments = ['model' => $table['model'], 'type'=>'scaffold'];
            if($key != $last_key){
                $arguments['--migrate'] = 'true';
            }

            $this->call('infyom:rollback', $arguments);

            if (isset($table['api']) && $table['api'] === true) {
                $arguments['type'] = 'api';
                $this->call('infyom:rollback', $arguments);
            }
        }

//        if ($this->checkIsThereAnyDataToGenerate()) {
//            $this->generateCommonItems();
//
//            $this->generateScaffoldItems();
//
//            $this->performPostActionsWithMigration();
//        } else {
//            $this->commandData->commandInfo('There isn not input fields to generate.');
//        }
    }
//
//    /**
//     * Get the console command options.
//     *
//     * @return array
//     */
//    public function getOptions()
//    {
//        return array_merge(parent::getOptions(), [
//
//        ]);
//    }
//
//    /**
//     * Get the console command arguments.
//     *
//     * @return array
//     */
//    protected function getArguments()
//    {
//        return array_merge(parent::getArguments(), []);
//    }
//
//    /**
//     * Check if there is anything to generate.
//     *
//     * @return bool
//     */
//    protected function checkIsThereAnyDataToGenerate()
//    {
//        if (count($this->commandData->fields) > 1) {
//            return true;
//        }
//    }
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
        ];
    }
}
