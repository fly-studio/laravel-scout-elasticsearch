<?php

namespace Addons\Elasticsearch\Scout;

/**
 * Class Indexable
 * @package Addons\Elasticsearch\Scout
 */
trait Indexable
{

    public $inIndex = null;

    /**
     * @param bool $includeBody
     * @return array
     */
    protected function getEsParams($includeBody = false)
    {
        $params = [
            'index' => $this->searchableAs(),
            'type' => '_doc',
            'id' => $this->getKey(),
        ];
        if ($includeBody)
            $params['body'] = $this->toArray();

        return $params;
    }

    /**
     * @throws \Exception
     */
    protected function checkDocument()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
    }

    /**
     * @return array|bool|null
     */
    public function inIndex()
    {
        $this->checkDocument();

        if (is_null($this->inIndex))
            $this->inIndex = app('elasticsearch')->connection()->exists($this->getEsParams());

        return $this->inIndex;
    }


    /**
     * @return array
     */
    public function addToIndex()
    {
        $this->checkDocument();

        if (!$this->inIndex())
        {
            $this->inIndex = null;
            return app('elasticsearch')->connection()->index($this->getEsParams(true));
        }

        return $this->updateToIndex();
    }

    /**
     * @return array
     */
    public function resetToIndex()
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

        if ($this->inIndex())
        {
            $this->inIndex = false;
            return app('elasticsearch')->connection()->delete($this->getEsParams());
        }
        return null;
    }


    /**
     * @return array
     */
    public function updateToIndex()
    {
        $this->checkDocument();

        if ($this->inIndex())
        {
            $this->inIndex = null;
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
