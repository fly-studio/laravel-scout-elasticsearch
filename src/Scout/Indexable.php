<?php

namespace Addons\Elasticsearch\Scout;

/**
 * Class Indexable
 * @package Addons\Elasticsearch\Scout
 */
trait Indexable
{

    public $hasIndex = null;

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

    public function checkDocument()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
    }


    public function hasIndex()
    {
        $this->checkDocument();
        if (is_null($this->hasIndex)) {
            $this->hasIndex = app('elasticsearch')->connection()->exists($this->getEsParams());
        }
        return $this->hasIndex;
    }


    public function addToIndex()
    {
        $this->checkDocument();
        if (!$this->hasIndex()) {
            $this->hasIndex = null;
            return app('elasticsearch')->connection()->index($this->getEsParams(true));
        }
        return null;
//        return $this->updateIndex();
    }


    public function reindex()
    {
        $this->checkDocument();
        $this->removeFromIndex();
        return $this->addToIndex();
    }

    public function removeFromIndex()
    {
        $this->checkDocument();
        if ($this->hasIndex()) {
            $this->hasIndex = false;
            return app('elasticsearch')->connection()->delete($this->getEsParams());
        }
        return null;
    }


    public function updateIndex()
    {
        $this->checkDocument();
        if ($this->hasIndex()) {
            $this->hasIndex = null;
            return app('elasticsearch')->connection()->update($this->getEsParams(true));
        }
        return $this->addToIndex();
    }
}
