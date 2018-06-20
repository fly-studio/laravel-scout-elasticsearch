<?php

namespace Addons\Elasticsearch\Scout;

/**
 * Class Indexable
 * @package Addons\Elasticsearch\Scout
 */
trait Typeable
{

    public $inType = null;

    /**
     * @param bool $includeBody
     * @return array
     */
    protected function getEsParams($includeBody = false)
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
    protected function checkDocument()
    {
        if (!$this->exists) {
            throw new \Exception('Document does not exist.');
        }
    }

    /**
     * @return array|bool|null
     */
    public function inType()
    {
        $this->checkDocument();

        if (is_null($this->inType))
            $this->inType = app('elasticsearch')->connection()->exists($this->getEsParams());

        return $this->inType;
    }


    /**
     * @return array
     */
    public function addToType()
    {
        $this->checkDocument();

        if (!$this->inType())
        {
            $this->inType = null;
            return app('elasticsearch')->connection()->index($this->getEsParams(true));
        }

        return $this->updateToType();
    }

    /**
     * @return array
     */
    public function resetToType()
    {
        $this->removeFromType();
        return $this->addToType();
    }

    /**
     * @return array|null
     */
    public function removeFromType()
    {
        $this->checkDocument();

        if ($this->inType())
        {
            $this->inType = false;
            return app('elasticsearch')->connection()->delete($this->getEsParams());
        }
        return null;
    }


    /**
     * @return array
     */
    public function updateToType()
    {
        $this->checkDocument();

        if ($this->inType())
        {
            $this->inType = null;
            $params = $this->getEsParams(true);
            $body = $params['body'];
            $params['body'] = [
                'doc' => $body
            ];
            return app('elasticsearch')->connection()->update($params);
        }

        return $this->addToType();
    }
}
