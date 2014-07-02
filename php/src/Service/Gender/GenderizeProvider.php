<?php

namespace Service\Gender;

class GenderizeProvider implements GenderProviderInterface
{
    const URL_TEMPLATE = 'http://api.genderize.io/{?name,names*,country_id,language_id}';

    /**
     * @var \Guzzle\ClientInterface $http_client
     */
    protected $http_client;

    /**
     * Constructor
     *
     * @param \Guzzle\ClientInterface $http_client
     */
    public function __construct($http_client)
    {
        $this->http_client = $http_client;
    }

    public function guess($name, $country_id = null, $language_id = null) {
        if (empty($name)) {
            return;
        }

        $data = is_array($name)
            ? array('names' => array('name' => $name))
            : array('name' => $name);

        if (isset($country_id)) {
            $data['country_id'] = $country_id;
        }
        if (isset($language_id)) {
            $data['language_id'] = $language_id;
        }

        $request = $this->http_client->createRequest('GET',
                                                     array(self::URL_TEMPLATE, $data));

        // var_dump($request->getUrl());
        $response = $this->http_client->send($request);
        if (200 != $response->getStatusCode()) {
            return;
        }

        $result = json_decode((string)$response->getBody());

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'genderize';
    }

}
