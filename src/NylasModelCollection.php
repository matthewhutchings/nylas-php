<?php
namespace Nylas;

class NylasModelCollection
{
    protected bool $isAdmin;
    private $chunkSize = 500;

    public function __construct($klass, $api, $namespace = NULL, $filter = [], $offset = 0, $filters = [], $admin = false)
    {
        $this->klass = $klass;
        $this->api = $api;
        $this->namespace = $namespace;
        $this->filter = $filter;
        $this->filters = $filters;
        $this->isAdmin = $admin;

        if (!array_key_exists('offset', $filter)) {
            $this->filter['offset'] = 0;
        }
    }

    public function items()
    {
        $offset = 0;

        while (true) {
            $items = $this->_getModelCollection($offset, $this->chunkSize);

            if (!$items) {
                break;
            }

            foreach ($items as $item) {
                yield $item;
            }

            $offset += count($items);
        }
    }

    public function first()
    {
        $results = $this->_getModelCollection(0, 1);

        if ($results) {
            return $results[0];
        }

        return NULL;
    }

    public function all($limit = INF)
    {
        return $this->range($this->filter['offset'], $limit);
    }

    public function where($filter, $filters = [])
    {
        $this->filter = array_merge($this->filter, $filter);
        $this->filter['offset'] = 0;
        $collection = clone $this;
        $collection->filter = $this->filter;

        return $collection;
    }

    public function find($id)
    {
        return $this->_getModel($id);
    }

    public function create($data)
    {
        return $this->klass->create($data, $this);
    }

    public function update($data, $id)
    {
        return $this->klass->update($data, $id, $this);
    }

    public function range($offset, $limit)
    {
        $result = [];

        while (count($result) < $limit) {
            $to_fetch = min($limit - count($result), $this->chunkSize);
            $data = $this->_getModelCollection($offset+count($result), $to_fetch);
            $result = array_merge($result, $data);

            if (!$data) {
                break;
            }
        }

        return $result;
    }

    private function _getModel($id)
    {
        // make filter a kwarg filters
        return $this->api->getResource($this->namespace, $this->klass, $id, $this->filter);
    }

    private function _getModelCollection($offset, $limit)
    {
        $this->filter['offset'] = $offset;
        $this->filter['limit'] = $limit;

        if ($this->isAdmin) {
            return $this->api->getAdminResources($this->namespace, $this->klass, $this->filter);
        }

        return $this->api->getResources($this->namespace, $this->klass, $this->filter);
    }

}
