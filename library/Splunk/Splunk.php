<?php
/* Icinga Web 2 Splunk Module (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Splunk;

use Icinga\Data\Selectable;

class Splunk implements Selectable
{
    protected $client;

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     *
     * @return  Query
     */
    public function select(array $columns = [])
    {
        return new Query($this, $columns);
    }

    /**
     * @return  object
     */
    public function getConfig()
    {
        return $this->config;
    }

    public static function extractFields($source, &$fields, array $parent = [])
    {
        foreach ($source as $key => $value) {
            if ($key === '_time') {
                $fields['_time'] = function($event) {
                    $value = new \DateTime($event['_time']);
                    return $value->format('Y-m-d H:i:s.v e');   # 2017-11-22 11:59:04.000 CET
                };
                continue;
            }
            if (is_array($value)) {
                static::extractFields($value, $fields, array_merge($parent, [$key]));
            } else {
                $field = array_merge($parent, [$key]);
                $fields[implode('_', $field)] = call_user_func(function($field) {
                    return function($event) use ($field) {
                        if (empty ($field)) {
                            return null;
                        }

                        $value = $event;

                        foreach ($field as $key) {
                            if (! isset($value[$key])) {
                                return null;
                            }

                            $value = $value[$key];
                        }

                        return $value;
                    };
                }, $field);
            }
        }
    }
}
