<?php

namespace Bkwld\Croppa;

use Illuminate\Container\Container;
use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\FileExistsException;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Interact with filesystems
 */
class Storage
{
    /**
     * @var Container
     */
    private $app;

    /**
     * @var array
     */
    private $config;

    /**
     * @var Filesystem | League\Flysystem\Cached\CachedAdapter
     */
    private $crops_disk;

    /**
     * @var Filesystem | League\Flysystem\Cached\CachedAdapter
     */
    private $src_disk;

    /**
     * Inject dependencies
     *
     * @param Container
     * @param array $config
     */
    public function __construct($app = null, $config = null)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Factory function to create an instance and then "mount" disks
     *
     * @param Container
     * @param array $config
     *
     * @return Bkwld\Croppa\Storage
     */
    static public function make($app, $config)
    {
        return with(new static($app, $config))->mount();
    }

    /**
     * "Mount" disks given the config
     *
     * @return $this
     */
    public function mount()
    {
        $this->setSrcDisk($this->makeDisk($this->config['src_dir']));
        $this->setCropsDisk($this->makeDisk($this->config['crops_dir']));

        return $this;
    }

    /**
     * Use or instantiate a Flysystem disk
     *
     * @param string $dir The value from one of the config dirs
     *
     * @return Filesystem|League\Flysystem\Cached\CachedAdapter
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function makeDisk($dir)
    {
        // Check if the dir refers to an IoC binding and return it
        if ($this->app->bound($dir)
            && ($instance = $this->app->make($dir))
            && (is_a($instance, 'Filesystem')
                || is_a($instance, 'League\Flysystem\Cached\CachedAdapter'))
        ) {
            return $instance;
        }

        // Instantiate a new Flysystem instance for local dirs
        return new Filesystem(new Adapter($dir));
    }

    /**
     * Return whether crops are stored remotely
     *
     * @return boolean
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function cropsAreRemote()
    {
        $adapter = $this->getCropsDisk()->getAdapter();

        // If using a cached adapter, get the actual adapter that is being cached.
        if (is_a($adapter, 'League\Flysystem\Cached\CachedAdapter')) {
            $adapter = $adapter->getAdapter();
        }

        // Check if the crop disk is not local
        return !is_a($adapter, 'League\Flysystem\Adapter\Local');
    }

    /**
     * Get the crops disk or make via the config
     *
     * @return Filesystem|League\Flysystem\Cached\CachedAdapter
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function getCropsDisk()
    {
        if (empty($this->crops_disk)) {
            $this->setCropsDisk($this->makeDisk($this->config['crops_dir']));
        }

        return $this->crops_disk;
    }

    /**
     * Set the crops disk
     *
     * @param  Filesystem|League\Flysystem\Cached\CachedAdapter
     */
    public function setCropsDisk($disk)
    {
        $this->crops_disk = $disk;
    }

    /**
     * Check if a remote crop exists
     *
     * @param string $path
     *
     * @return boolean
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function cropExists($path)
    {
        return $this->getCropsDisk()->has($path);
    }

    /**
     * Get the src image data or throw an exception
     *
     * @param string $path Path to image relative to dir
     *
     * @throws Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @return string
     */
    public function readSrc($path)
    {
        $disk = $this->getSrcDisk();
        if ($disk->has($path)) {
            return $disk->read($path);
        } else {
            throw new NotFoundHttpException('Croppa: Src image is missing');
        }
    }

    /**
     * Get the src disk or make via the config
     *
     * @return Filesystem|League\Flysystem\Cached\CachedAdapter
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function getSrcDisk()
    {
        if (empty($this->src_disk)) {
            $this->setSrcDisk($this->makeDisk($this->config['src_dir']));
        }

        return $this->src_disk;
    }

    /**
     * Set the src disk
     *
     * @param  Filesystem|League\Flysystem\Cached\CachedAdapter
     */
    public function setSrcDisk($disk)
    {
        $this->src_disk = $disk;
    }

    /**
     * Write the cropped image contents to disk
     *
     * @param string $path     Where to save the crop
     * @param string $contents The image data
     *
     * @throws Exception
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @return void
     */
    public function writeCrop($path, $contents)
    {
        try {
            $this->getCropsDisk()->write($path, $contents);
        } catch (FileExistsException $e) {
            // don't throw exception anymore as mentioned in PR #164
        }

    }

    /**
     * Get a local crops disks absolute path
     *
     * @return string
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function getLocalCropsDirPath()
    {
        return $this->getCropsDisk()->getAdapter()->getPathPrefix();
    }

    /**
     * Delete src image
     *
     * @param string $path Path to src image
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function deleteSrc($path)
    {
        $this->getSrcDisk()->delete($path);
    }

    /**
     * Delete crops
     *
     * @param  string $path Path to src image
     *
     * @return array List of crops that were deleted
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function deleteCrops($path)
    {
        $crops = $this->listCrops($path);
        $disk = $this->getCropsDisk();
        foreach ($crops as $crop) {
            $disk->delete($crop);
        }

        return $crops;
    }

    /**
     * Find all the crops that have been generated for a src path
     *
     * @param  string $path Path to a src image
     *
     * @return array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function listCrops($path)
    {

        // Get the filename and dir
        $filename = basename($path);
        $dir = dirname($path);
        if ($dir === '.') {
            $dir = '';
        } // Flysystem doesn't like "." for the dir

        // Filter the files in the dir to just crops of the image path
        return $this->justPaths(array_filter($this->getCropsDisk()->listContents($dir),
            function ($file) use ($filename) {

                // Don't return the source image, we're JUST getting crops
                return $file['basename'] != $filename

                    // Test that the crop begins with the src's path, that the crop is FOR
                    // the src
                    && strpos($file['basename'], pathinfo($filename, PATHINFO_FILENAME)) === 0

                    // Make sure that the crop matches that Croppa file regex
                    && preg_match('#' . URL::PATTERN . '#', $file['path']);
            }));
    }

    /**
     * Take a an array of results from Flysystem's listContents and get a simpler
     * array of paths to the files, relative to the crops_dir
     *
     * @param  array $files A multi-dimensionsal array from flysystem
     *
     * @return array $paths
     */
    protected function justPaths($files)
    {

        // Reset the indexes to be 0 based, mostly for unit testing
        $files = array_values($files);

        // Get just the path key
        return array_map(function ($file) {
            return $file['path'];
        }, $files);
    }

    /**
     * Delete ALL crops
     *
     * @param  string  $filter  A regex pattern
     * @param  boolean $dry_run Don't actually delete any
     *
     * @return array List of crops that were deleted
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function deleteAllCrops($filter = null, $dry_run = false)
    {
        $crops = $this->listAllCrops($filter);
        $disk = $this->getCropsDisk();
        if (!$dry_run) {
            foreach ($crops as $crop) {
                $disk->delete($crop);
            }
        }

        return $crops;
    }

    /**
     * Find all the crops witin the crops dir, optionally applying a filtering
     * regex to them
     *
     * @param  string $filter A regex pattern
     *
     * @return array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function listAllCrops($filter = null)
    {
        return $this->justPaths(array_filter($this->getCropsDisk()->listContents(null, true),
            function ($file) use ($filter) {

                // If there was a filter, force it to match
                if ($filter && !preg_match("#$filter#i", $file['path'])) {
                    return;
                }

                // Check that the file matches the pattern and get at the parts to make to
                // make the path to the src
                if (!preg_match('#' . URL::PATTERN . '#', $file['path'], $matches)) {
                    return false;
                }
                $src = $matches[1] . '.' . $matches[5];

                // Test that the src file exists
                return $this->getSrcDisk()->has($src);
            }));
    }

    /**
     * Count up the number of crops that have already been created
     * and return true if they are at the max number.
     *
     * @param  string $path Path to the src image
     *
     * @return boolean
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function tooManyCrops($path)
    {
        if (empty($this->config['max_crops'])) {
            return false;
        }

        return count($this->listCrops($path)) >= $this->config['max_crops'];
    }
}
