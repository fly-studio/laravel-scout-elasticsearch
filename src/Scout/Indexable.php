<?php

namespace Addons\Elasticsearch\Scout;

/**
 * Class Indexable
 * @package Addons\Elasticsearch\Scout
 */
trait Indexable
{

    public $hasIndex = null;

    /**
     * @param bool $includeBody
     * @return array
     */
    public function getEsParams($includeBody = false)
    {
        $params = [
            'index' => app('elasticsearch')->getConfig('index'),
            'type' => $this->searchableAs(),
            'id' => $this->getKey(),
        ];
        if ($includeBody) {
            $params['body'] = $this->toArray();
        }
        return $params;
    }


    /**
     * @return null
     * @throws \Exception
     */
    public function hasIndex()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
        if (is_null($this->hasIndex)) {
            return $this->hasIndex =  app('elasticsearch')->connection()->exists($this->getEsParams());
        }
        return $this->hasIndex;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function addToIndex()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
        if ($this->hasIndex()) {
            return $this->updateIndex();
        }
        return  app('elasticsearch')->connection()->index($this->getEsParams(true));
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function reindex()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
        $this->removeFromIndex();
        return $this->addToIndex();
    }

    /**
     * @return null
     * @throws \Exception
     */
    public function removeFromIndex()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
        if ($this->hasIndex()) {
            return  app('elasticsearch')->connection()->delete($this->getEsParams());
        }
        return null;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function updateIndex()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
        if ($this->hasIndex()) {
            return  app('elasticsearch')->connection()->update($this->getEsParams(true));
        }
        return $this->addToIndex();
    }
}
