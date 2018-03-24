<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk;

use RuntimeException;
use Icinga\Data\Paginatable;
use Icinga\Data\Queryable;
use Icinga\Util\Json;
use iplx\Http\Client;
use iplx\Http\Request;
use iplx\Http\Uri;

class Query implements Queryable, Paginatable
{
    const MAX_RESULT_WINDOW = 10000;

    protected $splunk;

    protected $fields;

    protected $filter;

    protected $index;

    protected $limit;

    protected $offset;

    protected $response;

    protected $patch = [];

    public function __construct(Splunk $splunk, array $fields = [])
    {
        $this->splunk = $splunk;

        $this->fields = $fields;
    }

    /**
     * {@inheritdoc}
     *
     * @return  $this
     */
    public function from($target, array $fields = null)
    {
        $this->index = $target;

        if (! empty($fields)) {
            $this->fields = $fields;
        }

        return $this;
    }

    public function limit($count = null, $offset = null)
    {
        $this->limit = $count;
        $this->offset = $offset;

        return $this;
    }

    public function hasLimit()
    {
        return $this->limit !== null;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function hasOffset()
    {
        return $this->offset !== null;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function count()
    {
        $this->execute();

        $total = $this->response['hits']['total'];
        if ($total > self::MAX_RESULT_WINDOW) {
            return self::MAX_RESULT_WINDOW;
        }

        return $total;
    }

    public function filter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

/**
 * json_split_objects - Return an array of many JSON objects
 *
 * In some applications (such as PHPUnit, or salt), JSON output is presented as multiple
 * objects, which you cannot simply pass in to json_decode(). This function will split
 * the JSON objects apart and return them as an array of strings, one object per indice.
 *
 * @param string $json  The JSON data to parse
 *
 * @return array
 */
protected function json_split_objects($json)
{
    $q = FALSE;
    $len = strlen($json);
    for($l=$c=$i=0;$i<$len;$i++)
    {   
        $json[$i] == '"' && ($i>0?$json[$i-1]:'') != '\\' && $q = !$q;
        if(!$q && in_array($json[$i], array(" ", "\r", "\n", "\t"))){continue;}
        in_array($json[$i], array('{', '[')) && !$q && $l++;
        in_array($json[$i], array('}', ']')) && !$q && $l--;
        (isset($objects[$c]) && $objects[$c] .= $json[$i]) || $objects[$c] = $json[$i];
        $c += ($l == 0);
    }   
    return $objects;
}

    protected function execute()
    {
        if ($this->response === null) {
            $config = $this->splunk->getConfig();

            $client = new Client();

            $curl = [];

            if (! empty($config->ca)) {
                if (is_dir($config->ca)
                    || (is_link($config->ca) && is_dir(readlink($config->ca)))
                ) {
                    $curl[CURLOPT_CAPATH] = $config->ca;
                } else {
                    $curl[CURLOPT_CAINFO] = $config->ca;
                }
            }

            if (! empty($config->client_certificate)) {
                $curl[CURLOPT_SSLCERT] = $config->client_certificate;
            }

            if (! empty($config->client_private_key)) {
                $curl[CURLOPT_SSLCERT] = $config->client_private_key;
            }

            $uri = (new Uri("{$config->uri}/services/search/jobs/export?output_mode=json&search=search *"))
                ->withUserInfo($config->user, $config->password);

            $request = new Request(
                'GET',
                $uri,
                ['Content-Type' => 'application/json'],
		json_encode(array_filter(array_merge([
			'output_mode' => 'json',
			'search'     => 'search *'
                ], $this->patch), function ($part) { return $part !== null; }))
            );

	    $response_string = $client->send($request, ['curl' => $curl])->getBody();
	    $json_objects = $this->json_split_objects((string)$response_string);
	    $json_hits = json_decode("{ 'hits': { } }", true);
	    $json_hits['hits']['hits'] = $json_objects;
	    $json_hits['hits']['total'] = count($json_objects);
	    $json_encoded = (json_encode($json_hits));
	    $response = Json::decode((string) $json_encoded, true);
            if (isset($response['error'])) {
                throw new RuntimeException(
                    'Got error from Splunk: '. $response['error']['type'] . ': ' . $response['error']['reason']
                );
            }

            $this->response = $response;
        }
    }

    public function getFields()
    {
        $this->execute();
        $events = $this->response['hits']['hits'];
        $fields = [];

        if (! empty($events)) {
		$event = reset($events);
		Splunk::extractFields(json_decode($event, true)["result"], $fields);
        }

        return $fields;
    }

    public function fetchAll()
    {
        $this->execute();

        return $this->response['hits']['hits'];
    }

    public function patch(array $patch)
    {
        $this->patch = $patch;

        return $this;
    }

    public function getResponse()
    {
        $this->execute();

        return $this->response;
    }

    public function get($target)
    {
        $config = $this->splunk->getConfig();

        $client = new Client();

        $curl = [];

        if (! empty($config->ca)) {
            if (is_dir($config->ca)
                || (is_link($config->ca) && is_dir(readlink($config->ca)))
            ) {
                $curl[CURLOPT_CAPATH] = $config->ca;
            } else {
                $curl[CURLOPT_CAINFO] = $config->ca;
            }
        }

        if (! empty($config->client_certificate)) {
            $curl[CURLOPT_SSLCERT] = $config->client_certificate;
        }

        if (! empty($config->client_private_key)) {
            $curl[CURLOPT_SSLCERT] = $config->client_private_key;
        }

        $curl[CURLOPT_SSL_VERIFYPEER] = false;
        $curl[CURLOPT_SSL_VERIFYHOST] = false;

        $uri = (new Uri("{$config->uri}/{$target}"))
            ->withUserInfo($config->user, $config->password);

        $request = new Request(
            'GET',
            $uri,
            ['Content-Type' => 'application/json']
        );

        $response = Json::decode((string) $client->send($request, ['curl' => $curl])->getBody(), true);

        if (isset($response['error'])) {
            throw new RuntimeException(
                'Got error from Splunk: '. $response['error']['type'] . ': ' . $response['error']['reason']
            );
        }

        return $response;
    }
}
