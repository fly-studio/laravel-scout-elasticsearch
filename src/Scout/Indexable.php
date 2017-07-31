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
     * @throws \Exception
     */
    public function checkDocument()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
    }


    /**
     * @return array|bool|null
     */
    public function hasIndex()
    {
        $this->checkDocument();
        if (is_null($this->hasIndex)) {
            $this->hasIndex = app('elasticsearch')->connection()->exists($this->getEsParams());
        }
        return $this->hasIndex;
    }


    /**
     * @return array
     */
    public function addToIndex()
    {
        $this->checkDocument();
        if (!$this->hasIndex()) {
            $this->hasIndex = null;
            return app('elasticsearch')->connection()->index($this->getEsParams(true));
        }
        return $this->updateIndex();
    }

    /**
     * @return array
     */
    public function reindex()
    {
        $this->removeFromIndex();
        return $this->addToIndex();
    }

    /**
     * @return array|null
     */
    public function removeFromIndex()
    {
        $this->checkDocument();
        if ($this->hasIndex()) {
            $this->hasIndex = false;
            return app('elasticsearch')->connection()->delete($this->getEsParams());
        }
        return null;
    }


    /**
     * @return array
     */
    public function updateIndex()
    {
        $this->checkDocument();
        if ($this->hasIndex()) {
            $this->hasIndex = null;
            $params = $this->getEsParams(true);
            $body = $params['body'];
            $params['body'] = [
                'doc' => $body
            ];
            return app('elasticsearch')->connection()->update($params);
        }
        return $this->addToIndex();
    }
}
