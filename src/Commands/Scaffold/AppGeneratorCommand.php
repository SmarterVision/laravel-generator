<?php

namespace InfyOm\Generator\Commands\Scaffold;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InfyOm\Generator\Commands\BaseCommand;
use InfyOm\Generator\Common\CommandData;
use Symfony\Component\Console\Input\InputArgument;

class AppGeneratorCommand extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'infyom:app';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a full Application for given models';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->commandData = new CommandData($this, CommandData::$COMMAND_TYPE_SCAFFOLD);
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $schema = config('app_generator.schema', []);
        $array_keys = array_keys($schema);
        $last_key = end($array_keys);
        foreach ($schema as $key => $table) {
            $arguments = ['model' => $table['model'], '--fieldsFile' => base_path($table['fieldsFile'])];
            if (isset($table['api']) && $table['api'] === true) {
                $this->call('infyom.api:controller', $arguments);
            }
            if ($key != $last_key) {
                $arguments['--migrate'] = 'true';
            }
            if (isset($table['skip']) && $table['skip'] === true) {
                $arguments['--skip'] = '*';
            }
            $this->call('infyom:scaffold', $arguments);
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
//            ['api', null, InputOption::VALUE_NONE, 'Specify if generate api of models'],
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
        return [];
    }
}
