<?php
namespace Icicle\Loop\Structures;

use SplObjectStorage;

class UnreferencableObjectStorage extends SplObjectStorage
{
    /**
     * @var SplObjectStorage
     */
    private $unreferenced;
    
    /**
     */
    public function __construct()
    {
        $this->unreferenced = new SplObjectStorage();
    }
    
    /**
     * @param   object $object
     */
    public function detach($object)
    {
        parent::detach($object);
        $this->unreferenced->detach($object);
    }
    
    /**
     * @param   object $object
     */
    public function offsetUnset($object)
    {
        parent::offsetUnset($object);
        $this->unreferenced->detach($object);
    }
    
    /**
     * @param   object $object
     */
    public function unreference($object)
    {
        if ($this->contains($object)) {
            $this->unreferenced->attach($object);
        }
    }
    
    /**
     * @param   object $object
     */
    public function reference($object)
    {
        $this->unreferenced->detach($object);
    }
    
    /**
     * @param   object $object
     *
     * @return  bool
     */
    public function referenced($object)
    {
        return $this->contains($object) && !$this->unreferenced->contains($object);
    }
    
    /**
     * @param   SplObjectStorage $storage
     */
    public function addAll($storage)
    {
        parent::addAll($storage);
        
        if ($storage instanceof self) {
            $this->unreferenced->addAll($storage->unreferenced);
        }
    }
    
    /**
     * @param   SplObjectStorage $storage
     */
    public function removeAll($storage)
    {
        parent::removeAll($storage);
        $this->unreferenced->removeAll($storage);
    }
    
    /**
     * @param   SplObjectStorage $storage
     */
    public function removeAllExcept($storage)
    {
        parent::removeAllExcept($storage);
        $this->unreferenced->removeAllExcept($storage);
    }
    
    /**
     * Returns the number of referenced objects in the storage.
     *
     * @return  int
     */
    public function count()
    {
        return parent::count() - $this->unreferenced->count();
    }
    
    /**
     * Returns the total number of objects in the storage.
     *
     * @return  int
     */
    public function total()
    {
        return parent::count();
    }
    
    /**
     * @return  bool
     */
    public function isEmpty()
    {
        return 0 === parent::count();
    }
}
