<?php
/* Icinga Web 2 Splunk Module | (c) 2016 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk\RestApi;

use ArrayIterator;
use LogicException;
use Icinga\Application\Benchmark;
use Icinga\Data\Extensible;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Reducible;
use Icinga\Data\Selectable;
use Icinga\Data\Updatable;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotImplementedError;
use Icinga\Exception\StatementException;
use Icinga\Exception\QueryException;
use Icinga\Web\UrlParams;
use Icinga\Module\Splunk\Exception\RestApiException;

class RestApiClient implements Extensible, Reducible, Selectable, Updatable
{
    /**
     * The cURL handle of this RestApiClient
     *
     * @var resource
     */
    protected $curl;

    /**
     * The host of the API
     *
     * @var string
     */
    protected $host;

    /**
     * The name of the user to access the API with
     *
     * @var string
     */
    protected $user;

    /**
     * The password for the user the API is accessed with
     *
     * @var string
     */
    protected $pass;

    /**
     * The path of a file holding one or more certificates to verify the peer with
     *
     * @var string
     */
    protected $certificatePath;

    /**
     * Create a new RestApiClient
     *
     * @param   string  $host               The host of the API
     * @param   string  $user               The name of the user to access the API with
     * @param   string  $pass               The password for the user the API is accessed with
     * @param   string  $certificatePath    The path of a file holding one or more certificates to verify the peer with
     */
    public function __construct($host, $user = null, $pass = null, $certificatePath = null)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->certificatePath = $certificatePath;
    }

    /**
     * Return the cURL handle of this RestApiClient
     *
     * @return  resource
     */
    public function getConnection()
    {
        if ($this->curl === null) {
            $this->curl = $this->createConnection();
        }

        return $this->curl;
    }

    /**
     * Create and return a new cURL handle for this RestApiClient
     *
     * @return  resource
     */
    protected function createConnection()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if ($this->certificatePath !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $this->certificatePath);
        }

        if ($this->user !== null && $this->pass !== null) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
        }

        return $curl;
    }

    /**
     * Send the given request and return its response
     *
     * @param   RestApiRequest  $request
     *
     * @return  RestApiResponse
     *
     * @throws  RestApiException            In case an error occured while handling the request
     */
    public function request(RestApiRequest $request)
    {
        $scheme = strpos($this->host, '://') !== false ? '' : 'http://';
        $path = '/' . join('/', array_map('rawurlencode', explode('/', ltrim($request->getPath(), '/'))));
        $query = ($request->getParams()->isEmpty() ? '' : ('?' . (string) $request->getParams()));

        $curl = $this->getConnection();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request->getHeaders());
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($curl, CURLOPT_URL, $scheme . $this->host . $path . $query);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request->getPayload());

        $result = curl_exec($curl);
        if ($result === false) {
            $restApiException = new RestApiException(curl_error($curl));
            $restApiException->setErrorCode(curl_errno($curl));
            throw $restApiException;
        }

        $header = substr($result, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
        $result = substr($result, curl_getinfo($curl, CURLINFO_HEADER_SIZE));

        $statusCode = 0;
        foreach (explode("\r\n", $header) as $headerLine) {
            // The headers are inspected manually because curl_getinfo($curl, CURLINFO_HTTP_CODE)
            // returns only the first status code. (e.g. 100 instead of 200)
            $matches = array();
            if (preg_match('/^HTTP\/[0-9.]+ ([0-9]+)/', $headerLine, $matches)) {
                $statusCode = (int) $matches[1];
            }
        }

        $response = new RestApiResponse($statusCode);
        if ($result) {
            $response->setPayload($result);
            $response->setContentType(curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
        }

        return $response;
    }

    /**
     * Create and return a new query for this RestApiClient
     *
     * @param   array   $indices    An array of index name patterns
     * @param   array   $types      An array of document type names
     *
     * @return  RestApiQuery
     */
    public function select(array $indices = null, array $types = null)
    {
        $query = new RestApiQuery($this);
        if ($indices !== null) {
            $query->setIndices($indices);
        }
        if ($types !== null) {
            $query->setTypes($types);
        }
        return $query;
    }

    /**
     * Fetch and return all documents of the given query's result set using an iterator
     *
     * @param   RestApiQuery    $query  The query returning the result set
     *
     * @return  ArrayIterator
     */
    public function query(RestApiQuery $query)
    {
        return new ArrayIterator($this->fetchAll($query));
    }

    /**
     * Count all documents of the result set
     *
     * @param   RestApiQuery    $query
     *
     * @return  int
     */
    public function count(RestApiQuery $query)
    {
        $response = $this->request($query->createCountRequest());
        if (! $response->isSuccess()) {
            throw new QueryException($this->renderErrorMessage($response));
        }

        return $query->createCountResult($response);
    }

    /**
     * Retrieve an array containing all documents of the result set
     *
     * @param   RestApiQuery    $query
     *
     * @return  array
     */
    public function fetchAll(RestApiQuery $query)
    {
        $response = $this->request($query->createSearchRequest());
        if (! $response->isSuccess()) {
            throw new QueryException($this->renderErrorMessage($response));
        }

        return $query->createSearchResult($response);
    }

    /**
     * Fetch the first document of the result set
     *
     * @param   RestApiQuery    $query
     *
     * @return  array|false
     */
    public function fetchRow(RestApiQuery $query)
    {
        $clonedQuery = clone $query;
        $clonedQuery->limit(1);
        $results = $this->fetchAll($clonedQuery);
        return array_shift($results) ?: false;
    }

    /**
     * Fetch the first field of all documents of the result set as an array
     *
     * @param   RestApiQuery    $query
     *
     * @return  array
     *
     * @throws  LogicException      In case no attribute is being requested
     */
    public function fetchColumn(RestApiQuery $query)
    {
        $fields = $query->getColumns();
        if (empty($fields)) {
            throw new LogicException('You must request at least one attribute when fetching a single field');
        }

        $results = $this->fetchAll($query);
        $alias = key($fields);
        $field = is_int($alias) ? current($fields) : $alias;
        $values = array();
        foreach ($results as $document) {
            if (isset($document->$field)) {
                $values[] = $document->$field;
            }
        }

        return $values;
    }

    /**
     * Fetch the first field of the first document of the result set
     *
     * @param   RestApiQuery    $query
     *
     * @return  string
     */
    public function fetchOne(RestApiQuery $query)
    {
        throw new NotImplementedError('RestApiClient::fetchOne() is not implemented yet');
    }

    /**
     * Fetch all documents of the result set as an array of key-value pairs
     *
     * The first field is the key, the second field is the value.
     *
     * @param   RestApiQuery    $query
     *
     * @return  array
     */
    public function fetchPairs(RestApiQuery $query)
    {
        throw new NotImplementedError('RestApiClient::fetchPairs() is not implemented yet');
    }

    /**
     * Fetch and return the given document
     *
     * In case you are only interested in the source, pass "_source" as the only desired field.
     *
     * @param   string      $index          The index the document is located in
     * @param   string      $documentType   The type of the document to fetch
     * @param   string      $id             The id of the document to fetch
     * @param   array       $fields         The desired fields to return instead of all fields
     * @param   UrlParams   $params         Additional URL parameters to add to the request
     *
     * @return  object|false            Returns false in case no document could be found
     */
    public function fetchDocument($index, $documentType, $id, array $fields = null, UrlParams $params = null)
    {
        $request = new GetApiRequest($index, $documentType, $id);
        if ($params !== null) {
            $request->setParams($params);
        }

        if (! empty($fields)) {
            if (count($fields) == 1 && reset($fields) === '_source') {
                $request->setSourceOnly();
                $fields = null;
            } elseif (! $request->getParams()->has('_source')) {
                $request->getParams()->set('_source', join(',', $fields));
            }
        }

        $response = $this->request($request);
        if (! $response->isSuccess()) {
            if ($response->getStatusCode() === 404) {
                return false;
            }

            throw new QueryException($this->renderErrorMessage($response));
        }

        $hit = new SearchHit($response->json());
        return $hit->createRow($fields ?: array());
    }

    /**
     * Insert the given data for the given target
     *
     * @param   string|array    $target
     * @param   array           $data
     * @param   UrlParams       $params     Additional URL parameters to add to the request
     *
     * @return  bool    Whether the document has been created or not
     *
     * @throws  StatementException
     */
    public function insert($target, array $data, UrlParams $params = null)
    {
        if (is_string($target)) {
            $target = explode('/', $target);
        }

        switch (count($target)) {
            case 3:
                list($index, $documentType, $id) = $target;
                break;
            case 2:
                list($index, $documentType) = $target;
                $id = null;
                break;
            default:
                throw new LogicException('Invalid target "%s"', join('/', $target));
        }

        $request = new IndexApiRequest($index, $documentType, $id, $data);
        if ($params !== null) {
            $request->setParams($params);
        } else {
            $params = $request->getParams();
        }

        if (! $params->has('refresh')) {
            $params->set('refresh', true);
        }

        try {
            $response = $this->request($request);
        } catch (RestApiException $e) {
            throw new StatementException(
                'Failed to index document "%s". An error occurred: %s',
                join('/', $target),
                $e
            );
        }

        if (! $response->isSuccess()) {
            throw new StatementException(
                'Unable to index document "%s": %s',
                join('/', $target),
                $this->renderErrorMessage($response)
            );
        }

        $json = $response->json();
        return $json['created'];
    }

    /**
     * Update the target with the given data and optionally limit the affected documents by using a filter
     *
     * Note that the given filter will have no effect in case the target represents a single document.
     *
     * @param   string|array    $target
     * @param   array           $data
     * @param   Filter          $filter
     * @param   UrlParams       $params     Additional URL parameters to add to the request
     *
     * @return  array   The response for the requested update
     *
     * @throws  StatementException
     *
     * @todo    Add support for bulk updates
     */
    public function update($target, array $data, Filter $filter = null, UrlParams $params = null)
    {
        if (is_string($target)) {
            $target = explode('/', $target);
        }

        switch (count($target)) {
            case 3:
                list($index, $documentType, $id) = $target;
                break;
            case 2:
                if ($filter === null) {
                    throw new LogicException('Update requests without id are required to provide a filter');
                }

                list($index, $documentType) = $target;
                $id = null;
                break;
            default:
                throw new LogicException('Invalid target "%s"', join('/', $target));
        }

        if ($id !== null) {
            $request = new UpdateApiRequest($index, $documentType, $id, array('doc' => (object) $data));
        } elseif ($filter !== null) {
            $query = new RestApiQuery($this, array('_id'));
            $ids = $query
                ->setIndices(array($index))
                ->setTypes(array($documentType))
                ->setFilter($filter)
                ->fetchColumn();
            if (empty($ids)) {
                throw new StatementException('No documents found');
            } elseif (count($ids) == 1) {
                $request = new UpdateApiRequest($index, $documentType, $ids[0], array('doc' => (object) $data));
            } else {
                throw new NotImplementedError('Bulk updates are not supported yet');
            }
        }

        if ($params !== null) {
            $request->setParams($params);
        } else {
            $params = $request->getParams();
        }

        if (! $params->has('refresh')) {
            $params->set('refresh', true);
        }
        if (! $params->has('fields')) {
            $params->set('fields', '_source');
        }

        try {
            $response = $this->request($request);
        } catch (RestApiException $e) {
            throw new StatementException(
                'Failed to update document "%s". An error occurred: %s',
                join('/', $target),
                $e
            );
        }

        if (! $response->isSuccess()) {
            throw new StatementException(
                'Unable to update document "%s": %s',
                join('/', $target),
                $this->renderErrorMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Delete documents in the given target, optionally limiting the affected documents by using a filter
     *
     * Note that the given filter will have no effect in case the target represents a single document.
     *
     * @param   string|array    $target
     * @param   Filter          $filter
     * @param   UrlParams       $params     Additional URL parameters to add to the request
     *
     * @return  array   The response for the requested deletion
     *
     * @throws  StatementException
     *
     * @todo    Add support for bulk deletions
     */
    public function delete($target, Filter $filter = null, UrlParams $params = null)
    {
        if (is_string($target)) {
            $target = explode('/', $target);
        }

        switch (count($target)) {
            case 3:
                list($index, $documentType, $id) = $target;
                break;
            case 2:
                if ($filter === null) {
                    throw new LogicException('Update requests without id are required to provide a filter');
                }

                list($index, $documentType) = $target;
                $id = null;
                break;
            default:
                throw new LogicException('Invalid target "%s"', join('/', $target));
        }

        if ($id !== null) {
            $request = new DeleteApiRequest($index, $documentType, $id);
        } elseif ($filter !== null) {
            $query = new RestApiQuery($this, array('_id'));
            $ids = $query
                ->setIndices(array($index))
                ->setTypes(array($documentType))
                ->setFilter($filter)
                ->fetchColumn();
            if (empty($ids)) {
                throw new StatementException('No documents found');
            } elseif (count($ids) == 1) {
                $request = new DeleteApiRequest($index, $documentType, $ids[0]);
            } else {
                throw new NotImplementedError('Bulk deletions are not supported yet');
            }
        }

        if ($params !== null) {
            $request->setParams($params);
        } else {
            $params = $request->getParams();
        }

        if (! $params->has('refresh')) {
            $params->set('refresh', true);
        }

        try {
            $response = $this->request($request);
        } catch (RestApiException $e) {
            throw new StatementException(
                'Failed to delete document "%s". An error occurred: %s',
                join('/', $target),
                $e
            );
        }

        if (! $response->isSuccess()) {
            throw new StatementException(
                'Unable to delete document "%s": %s',
                join('/', $target),
                $this->renderErrorMessage($response)
            );
        }

        return $response->json();
    }

    /**
     * Render and return a human readable error message for the given error document
     *
     * @return  string
     *
     * @todo    Parse Splunk 2.x structured errors
     */
    public function renderErrorMessage(RestApiResponse $response)
    {
        try {
            $errorDocument = $response->json();
        } catch (IcingaException $e) {
            return sprintf('%s: %s',
                $e->getMessage(),
                $response->getPayload()
            );
        }

        if (! isset($errorDocument['error'])) {
            return sprintf('Splunk unknown json error %s: %s',
                $response->getStatusCode(),
                $response->getPayload()
            );
        }

        if (is_string($errorDocument['error'])) {
            return $errorDocument['error'];
        }

        return sprintf('Splunk json error %s: %s',
            $response->getStatusCode(),
             json_encode($errorDocument['error'])
        );
    }

    /**
     * Render and return the given filter as Splunk query
     *
     * @param   Filter  $filter
     *
     * @return  array
     */
    public function renderFilter(Filter $filter)
    {
        $renderer = new FilterRenderer($filter);
        return $renderer->getQuery();
    }

    /**
     * Retrieve columns from the Splunk indices.
     *
     * It will get you a merged list of columns available over the specified indices and types.
     *
     * @param   array   $indices    The indices or index patterns to get
     * @param   array   $types      An array of types to get columns for
     *
     * @throws  QueryException  When Splunk returns an error
     *
     * @return  array           A list of column names
     *
     * @todo    Do a cached retrieval?
     */
    public function fetchColumns(array $indices = null, array $types = null)
    {
        Benchmark::measure('Retrieving columns for types: ' . (!empty($types) ? join(', ', $types) : '(all)'));
        $request = new GetMappingApiRequest($indices, $types);

        $response = $this->request($request);
        if (! $response->isSuccess()) {
            if ($response->getStatusCode() === 404) {
                return false;
            }

            throw new QueryException($this->renderErrorMessage($response));
        }

        // initialize with interal columns
        $columns = array(
            '_index',
            '_type',
            '_id',
        );

        foreach ($response->json() as $index => $mappings) {
            if (! array_key_exists('mappings', $mappings)) {
                continue;
            }
            foreach ($mappings['mappings'] as $type) {
                if (! array_key_exists('properties', $type)) {
                    continue;
                }
                foreach ($type['properties'] as $column => $detail) {
                    if ($column === '@version') {
                        continue;
                    }
                    if (array_key_exists('properties', $detail)) {
                        // ignore structured types
                        // TODO: support this later?
                        continue;
                    }
                    if (! in_array($column, $columns)) {
                        $columns[] = $column;
                    }
                }

            }
        }
        Benchmark::measure('Finished retrieving columns');

        return $columns;
    }
}
