<?php

namespace Bkwld\Croppa\Commands;

use Bkwld\Croppa\Storage;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Delete ALL crops from the crops_dir
 */
class Purge extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'croppa:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete ALL crops';

    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @param Storage $storage
     */
    public function __construct(Storage $storage)
    {
        parent::__construct();

        $this->storage = $storage;
    }

    /**
     * Backwards compatability with Laravel 4
     *
     * @return void
     */
    public function fire()
    {
        $this->handle();
    }

    /**
     * Execute the console command
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function handle()
    {
        $dry = $this->input->getOption('dry-run');

        foreach ($this->storage->deleteAllCrops($this->input->getOption('filter'), $dry) as $path) {
            $this->info(sprintf('%s %s', $path, $dry ? 'not deleted' : 'deleted'));
        }
    }

    /**
     * Get the console command options
     *
     * @return array;
     */
    protected function getOptions()
    {
        return [
            ['filter', null, InputOption::VALUE_REQUIRED, 'A regex pattern that whitelists matching crop paths', null],
            ['dry-run', null, InputOption::VALUE_NONE, 'Only return the crops that would be deleted'],
        ];
    }
}
