<?php
/**
 * Created by PhpStorm.
 * User: Oleg
 * Date: 08.01.2019
 * Time: 21:48
 */

namespace App;

class FileFinder extends \Symfony\Component\Finder\Finder {
    protected $iterators = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Creates a new Finder.
     *
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Appends an existing set of files/directories to the finder.
     *
     * The set can be another Finder, an Iterator, an IteratorAggregate, or even a plain array.
     *
     * @param iterable $iterator
     *
     * @return $this
     *
     * @throws \InvalidArgumentException when the given argument is not iterable
     */
    public function append($iterator)
    {
        var_dump($iterator);
        die();
        if ($iterator instanceof \IteratorAggregate) {
            $this->iterators[] = $iterator->getIterator();
        } elseif ($iterator instanceof \Iterator) {
            $this->iterators[] = $iterator;
        } elseif ($iterator instanceof \Traversable || \is_array($iterator)) {
            $it = new \ArrayIterator();
            foreach ($iterator as $file) {
                $it->append($file instanceof \App\FileInfo ? $file : new \App\FileInfo($file));
            }
            $this->iterators[] = $it;
        } else {
            throw new \InvalidArgumentException('Finder::append() method wrong argument type.');
        }

        return $this;
    }
}