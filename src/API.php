<?php
/**
*     Copyright 2019 Robert Woodward.
 *
 *    Licensed under the Apache License, Version 2.0 (the "License");
 *    you may not use this file except in compliance with the License.
 *    You may obtain a copy of the License at

 *        http://www.apache.org/licenses/LICENSE-2.0

 *    Unless required by applicable law or agreed to in writing, software
 *    distributed under the License is distributed on an "AS IS" BASIS,
 *    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *    See the License for the specific language governing permissions and
 *    limitations under the License.
*/

/**
 * PHP wrapper class for the Arbor/Netscout SP REST and webservices API.
 */

namespace Arbor;

class API
{
    private $apiConf;
    private $hasError = false;
    private $errorMessage;

    /**
     * @param array $apiConf sets the configuration to connect to the
     *                       Abror API, ipaddress, hostname, wsapikey,
     *                       resttoken
     */
    public function __construct($apiConf)
    {
        $this->RestUrl = 'https://'.$apiConf['ipaddress'].'/api/sp/';
        $this->WSUrl = 'https://'.$apiConf['ipaddress'].'/arborws/';
        $this->apiConf = $apiConf;
    }

    /**
     * Get an object by it's ID.
     *
     * @param string endpoint Type of object to get. managed_object etc
     * @param string id   object ID
     *
     * @return object returns a json decoded object with the result
     */
    public function getByID($endpoint, $arborID)
    {
        return $this->doRESTCallByID($endpoint, $arborID);
    }

    /**
     * Find or search Arbor SP REST API for a particular record or set of
     * records. This wraps around the full api. API doesn't have a filter/searh
     * so we have to simulate it.
     *
     * @param string $endpoint endpoint type, Managed Object, Mitigations etc.
     *                         See Arbor API documenation for endpoint list.
     * @param string $field    Fields to search on, i.e. name, id.
     * @param string $search   search value to match on $field
     * @param int    $perPage  limit the number of returned objects per page
     *
     * @return string returns a json string with the records from the API
     */
    public function findRest($endpoint, $field = null, $search = null, $perPage = 50)
    {
        $result = [];
        $currentPage = 1;
        $totalPages = 1;

        // Do inital REST call to the API, this helps determin the number of
        // pages in the result.
        //
        $apiResult = $this->doPagedRESTCall($endpoint, $perPage, 1);

        // If there is an error return here.

        if ($this->hasError) {
            return;
        }

        //
        // Work out the number of pages.
        //
        if (isset($apiResult['links']['last'])) {
            parse_str(parse_url($apiResult['links']['last'])['query'], $parsed);
            $totalPages = $parsed['page'];
        }

        // Keep looping getting the next page until there are no
        // pages left.
        //
        while (true) {
            foreach ($apiResult['data'] as $r) {
                // If searching on a partucular field check for match.
                //
                if (null !== $field) {
                    if (isset($r['attributes'][$field])) {
                        if ($r['attributes'][$field] == $search) {
                            $result[] = $r;
                        }
                    }
                } else {
                    // Store result from API in results.
                    $result[] = $r;
                }
            }
            ++$currentPage;
            // Break out of the loop when we have done the last page.
            if ($currentPage > $totalPages) {
                break;
            }
            $apiResult = $this->doPagedRESTCall($endpoint, $perPage, $currentPage);

            // Check for an error but don't return here in case some results
            // have already been found.
            //
            if ($this->hasError) {
                break;
            }

            // Just in case the results change and we come to a blank page.
            //
            if (empty($apiResult)) {
                break;
            }
        }

        return $result;
    }

    /**
     * Gets multiple managed objects with optional search fields.
     *
     * @param string $field   AS number to search for
     * @param string $search  search string to match against
     * @param int    $perPage Number of pages to get from the server at a time. Default 50.
     *
     * @return string Returns a json string with the records from the API
     */
    public function getManagedObjects($field = null, $search = null, $perPage = 50)
    {
        return $this->findRest('managed_objects', $field, $search, $perPage);
    }

    /**
     * Create a new managed object.
     *
     * @param string $name            Name of the managed object to create
     * @param string $family          Managed object family: peer, profile or customer
     * @param string $tags            Tags to add the the managed object
     * @param string $matchType       what type this match is, cidr_blocks for example
     * @param string $match           what to match against
     * @param object $relationships   Object for relationships to this managed object. See Arbor SDK Docs.
     * @param object $extraAttributes Object for extra attributes to add to this managed object. See Arbor SDK Docs.
     *
     * @return object returns a json decoded object with the result
     */
    public function createManagedObject($name, $family, $tags, $matchType, $match, $relationships = null, $extraAttributes = null)
    {
        $url = $this->RestUrl.'/managed_objects/';

        // Disable host detection settings in relationship unless
        // this has been overridden by the relationships argument.
        //
        if (null === $relationships) {
            $relationships = [
                'shared_host_detection_settings' => [
                    'data' => [
<<<<<<< HEAD
                        'type' => 'shared_host_detection_settings',
                        'id' => '1',
=======
                        'type' => 'shared_host_detection_setting',
                        'id' => '0',
>>>>>>> master
                    ],
                ],
            ];
        }

        // Add in the required attributes for a managed object.
        //
        $requiredAttributes = [
            'name' => $name,
            'family' => $family,
            'tags' => $tags,
            'match' => $match,
            'match_type' => $matchType,
        ];

        // Merge in extra attributes for this managed object
        //
        if (null === $extraAttributes) {
            $attributes = $requiredAttributes;
        } else {
            $attributes = array_merge($requiredAttributes, $extraAttributes);
        }

        // Create the full managed object data to be converted to json.
        //
        $moJson = [
            'data' => [
                'attributes' => $attributes,
                'relationships' => $relationships,
            ],
        ];

        $dataString = json_encode($moJson);

        // Send the API request.
        //
        return $this->doCurlREST($url, 'POST', $dataString);
    }

    /**
     * Change a managed object.
     *
     * @param string $arborID    managed object ID to change
     * @param string $attributes Attributes to change on the managed object.
     *                           See Arbor API documentation for a full list of attributes.
     * @param object $relationships   Object for relationships to this managed object. See Arbor SDK Docs.
     *
     * @return object returns a json decoded object with the result
     */
    public function changeManagedObject($arborID, $attributes, $relationships = null)
    {
        $url = $this->RestUrl.'/managed_objects/'.$arborID;

        $moJson = [
            'data' => [
                'attributes' => $attributes,
            ],
        ];

        if ($relationships !== null) {
            $moJson['data']['relationships'] = $relationships;
        }

        $dataString = json_encode($moJson);

        // Send the API request.
        //
        return $this->doCurlREST($url, 'PATCH', $dataString);
    }

    /**
     * Gets multiple notification Groups with optional search.
     *
     * @param string $field   field to search
     * @param string $search  search string to match against
     * @param int    $perPage Number of pages to get from the server at a time. Default 50.
     *
     * @return string Returns a json string with the records from the API
     */
    public function getNotificationGroups($field = null, $search = null, $perPage = 50)
    {
        return $this->findRest('notification_groups', $field, $search, $perPage);
    }

    /**
     * Create a new managed object.
     *
     * @param string $name            Name of the managed object to create
     * @param array  $emailAddresses  array of email addresses to add to the notification group
     * @param object $extraAttributes Object for extra attributes to add to this notification group. See Arbor SDK Docs.
     *
     * @return object returns a json decoded object with the result
     */
    public function createNotificationGroup($name, $emailAddresses = null, $extraAttributes = null)
    {
        $url = $this->RestUrl.'/notification_groups/';

        // Add in the required attributes for a notification group.
        //
        $requiredAttributes = ['name' => $name];

        if (isset($emailAddresses)) {
            $requiredAttributes['smtp_email_addresses'] = implode(',', $emailAddresses);
        }

        // Merge in extra attributes for this managed object
        //
        if (null === $extraAttributes) {
            $attributes = $requiredAttributes;
        } else {
            $attributes = array_merge($requiredAttributes, $extraAttributes);
        }

        // Create the full managed object data to be converted to json.
        //
        $ngJson = [
            'data' => [
                'attributes' => $attributes,
            ],
        ];

        $dataString = json_encode($ngJson);

        // Send the API request.
        //
        return $this->doCurlREST($url, 'POST', $dataString);
    }

    /**
     * Change a notification group.
     *
     * @param string $arborID    notification group ID to change
     * @param string $attributes attributes to change on the notifciation group
     *                           See Arbor API documentation for a full list of attributes
     *
     * @return object returns a json decoded object with the result
     */
    public function changeNotificationGroup($arborID, $attributes)
    {
        $url = $this->RestUrl.'/notification_groups/'.$arborID;

        $ngJson = [
            'data' => [
                'attributes' => $attributes,
            ],
        ];

        $dataString = json_encode($ngJson);

        // Send the API request.
        //
        return $this->doCurlREST($url, 'PATCH', $dataString);
    }

    /**
     * Get Peer Managed object traffic graph from arbor SP. This is a detail graph with in, out, total.
     *
     *
     * @param string $arborID   Arbor Managed Object ID
     * @param string $title     Title of the graph
     * @param string $startDate Start date for the graph
     * @param string $endDate   End date for the graph
     *
     * @return string returns a PNG image
     */
    public function getPeerTrafficGraph($arborID, $title, $startDate = '7 days ago', $endDate = 'now')
    {
        $filters = [
            ['type' => 'peer', 'value' => $arborID, 'binby' => false],
        ];

        $queryXML = $this->buildQueryXML($filters, $startDate, $endDate, 'bps', ['in', 'out', 'total']);
        $graphXML = $this->buildGraphXML($title, 'bps', true);

        return $this->getTrafficGraph($queryXML, $graphXML);
    }

    /**
     * Get ASN traffic graph traffic graph from arbor SP.
     *
     * @param string $ASnum     AS number
     * @param string $startDate Start date for the graph
     * @param string $endDate   End date for the graph
     *
     * @return string returns a PNG image
     */
    public function getASNTrafficGraph($ASN, $startDate = '7 days ago', $endDate = 'now')
    {
        $filters = [
            ['type' => 'aspath', 'value' => '_'.$ASN.'_', 'binby' => true],
        ];

        $queryXML = $this->buildQueryXML($filters, $startDate, $endDate);
        $graphXML = $this->buildGraphXML('Traffic to AS'.$ASN, 'bps (-In / +Out)', false, 986, 270);

        return $this->getTrafficGraph($queryXML, $graphXML);
    }

    /**
     * Get interface traffic graph from arbor SP.
     *
     * @param string $arborID   Arbor Managed Object ID
     * @param string $title     Title of the graph
     * @param string $startDate Start date for the graph
     * @param string $endDate   End date for the graph
     *
     * @return string returns a PNG image
     */
    public function getIntfTrafficGraph($arborID, $title, $startDate = '7 days ago', $endDate = 'now')
    {
        $filters = [
            ['type' => 'interface', 'value' => $arborID, 'binby' => false],
        ];

        $queryXML = $this->buildQueryXML($filters, $startDate, $endDate, 'bps', ['in', 'out', 'total', 'dropped', 'backbone']);
        $graphXML = $this->buildGraphXML($title, 'bps', true);

        return $this->getTrafficGraph($queryXML, $graphXML);
    }

    /**
     * Get traffic graph from arbor SP using the web services API.
     *
     *
     * @param string $queryXML Query XML string
     * @param string $graphXML Graph format XML string
     *
     * @return string returns a PNG image
     */
    public function getTrafficGraph($queryXML, $graphXML)
    {
        $url = $this->WSUrl.'/traffic/?api_key='.$this->apiConf['apikey']
            .'&graph='.rawurlencode($graphXML)
            .'&query='.rawurlencode($queryXML);

        $output = $this->doCurlWS($url);

        if ($this->hasError) {
            return;
        }

        $fileInfo = finfo_open();
        $mimeType = finfo_buffer($fileInfo, $output, FILEINFO_MIME_TYPE);

        if ('image/png' === $mimeType) {
            return $output;
        }

        // If we get here theres been an error on the graph. Errors usually come
        // out as XML for traffic queries.
        //
        $outXML = new \SimpleXMLElement($output);
        if ($outXML->error) {
            foreach ($outXML->error as $error) {
                $this->errorMessage .= (string) $error."\n";
            }
            $this->hasError = true;
        }
    }

    /**
     * Build XML for querying the Web Services API.
     *
     * @param array  $filters   filters array
     * @param string $startDate start date/time for data
     * @param string $endDate   end date/time for data
     * @param string $unitType  Units of data to gather. bps or pps.
     * @param array  $classes   Classes of data to gather. in, out, total, backbone, dropped.
     *
     * @return string returns a XML string used to Query the WS API
     */
    public function buildQueryXML($filters, $startDate = '7 days ago', $endDate = 'now', $unitType = 'bps', $classes = [])
    {
        $queryXML = $this->getBaseXML();
        $baseNode = $queryXML->firstChild;

        // Create Query Node.
        $queryNode = $queryXML->createElement('query');
        $queryNode->setAttribute('type', 'traffic');
        $baseNode->appendChild($queryNode);

        // Create time Node.
        $timeNode = $queryXML->createElement('time');
        $timeNode->setAttribute('end_ascii', $endDate);
        $timeNode->setAttribute('start_ascii', $startDate);
        $queryNode->appendChild($timeNode);

        // Create unit node.
        $unitNode = $queryXML->createElement('unit');
        $unitNode->setAttribute('type', $unitType);
        $queryNode->appendChild($unitNode);

        // Create search node.
        $searchNode = $queryXML->createElement('search');
        $searchNode->setAttribute('timeout', 30);
        $searchNode->setAttribute('limit', 100);
        $queryNode->appendChild($searchNode);

        // Add the class nodes
        if (!empty($classes)) {
            foreach ($classes as $class) {
                $classNode = $queryXML->createElement('class', $class);
                $queryNode->appendChild($classNode);
            }
        }

        // Add the filters.
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                if (isset($filter['type'])) {
                    $filterNode = $this->addQueryFilter($filter, $queryXML);
                    if ($filterNode) {
                        $queryNode->appendChild($filterNode);
                    }
                }
            }
        }

        return $queryXML->saveXML();
    }

    /**
     * Build XML for graph output.
     *
     * @param string $title  title of the graph
     * @param string $yLabel label for the Y-Axis on the graph
     * @param bool   $detail sets the graph to be a detail graph type when true
     * @param int    $width  graph width
     * @param int    $width  graph height
     *
     * @return string returns a XML string used to configure the graph returned by the WS API
     */
    public function buildGraphXML($title, $yLabel, $detail = false, $width = 986, $height = 180)
    {
        $graphXML = $this->getBaseXML();
        $baseNode = $graphXML->firstChild;

        $graphNode = $graphXML->createElement('graph');
        $graphNode->setAttribute('id', 'graph1');
        $baseNode->appendChild($graphNode);

        $graphNode->appendChild($graphXML->createElement('title', $title));
        $graphNode->appendChild($graphXML->createElement('ylabel', $yLabel));
        $graphNode->appendChild($graphXML->createElement('width', $width));
        $graphNode->appendChild($graphXML->createElement('height', $height));
        $graphNode->appendChild($graphXML->createElement('legend', 1));

        if (true === $detail) {
            $graphNode->appendChild($graphXML->createElement('type', 'detail'));
        }

        return $graphXML->saveXML();
    }

    /**
     * Do a API call to Arbor REST API to get a single object by ID.
     *
     * @param string $endpint Endpoint to query. See Arbor API documentation for endpoint list.
     * @param int    $arborID ID of the endpoint to find
     *
     * @return object returns a json decoded object with the records from the API
     */
    public function doRESTCallByID($endpoint, $arborID)
    {
        $url = $this->RestUrl.$endpoint.'/'.$arborID;

        return $this->doCurlREST($url);
    }

    /**
     * Do a paged API call to Arbor REST API. This gets one page of results at a time.
     *
     * @param string $endpint Endpoint to query. See Arbor API documentation for endpoint list.
     * @param string $perPage number of records to get
     * @param string $page    start record
     *
     * @return object returns a json decoded object with the records from the API
     */
    public function doPagedRESTCall($endpoint, $perPage = 50, $page = null)
    {
        $url = $this->RestUrl.$endpoint.'/?perPage='.$perPage;

        if (null !== $page) {
            $url .= '&page='.$page;
        }

        return $this->doCurlREST($url);
    }

    /**
     * Gets the current error state.
     *
     * @return bool true if there is a current error, false otherwise
     */
    public function hasError()
    {
        return $this->hasError;
    }

    /**
     * Gets the current error message string.
     *
     * @return string the error message string
     */
    public function errorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Perform a Curl request against the API.
     *
     * @return string the output of the API call, null otherwise
     */
    private function doCurlREST($url, $type = 'GET', $postData = null)
    {
        $this->hasError = false;
        $this->errorMessage = '';

        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, [
            'Content-Type: application/vnd.api+json',
            'X-Arbux-APIToken: '.$this->apiConf['resttoken'],
        ]);

        if ('PATCH' == $type || 'POST' === $type) {
            curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $type);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $postData);
        }

        curl_setopt($curlHandle, CURLOPT_URL, $url);

        $output = curl_exec($curlHandle);

        // Check if any curl error occurred
        if (curl_errno($curlHandle)) {
            $this->hasError = true;
            $this->errorMessage = curl_error($curlHandle);

            return;
        }

        if (empty($output)) {
            $this->hasError = true;
            $this->errorMessage = 'Server returned no data.';

            return;
        }

        $apiResult = json_decode($output, true);

        if (empty($apiResult)) {
            $this->hasError = true;
            $this->errorMessage = 'Unable to decode json output.';

            return;
        }

        if (isset($apiResult['errors']) && !empty($apiResult['errors'])) {
            $this->hasError = true;
            $this->findError($apiResult['errors']);

            return;
        }

        return $apiResult;
    }

    /**
     * Perform a Curl request against the Web Services API.
     *
     * @return string the output of the API call, null otherwise
     */
    private function doCurlWS($url)
    {
        $this->hasError = false;
        $this->errorMessage = '';

        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_URL, $url);

        $output = curl_exec($curlHandle);

        // Check if any curl error occurred
        if (curl_errno($curlHandle)) {
            $this->hasError = true;
            $this->errorMessage = curl_error($curlHandle);

            return;
        }

        if (empty($output)) {
            $this->hasError = true;
            $this->errorMessage = 'Server returned no data.';

            return;
        }

        return $output;
    }

    /**
     * Gets a base XML DOM document.
     *
     * @return object the DOM document to use as the base XML
     */
    private function getBaseXML()
    {
        $baseXML = new \DomDocument('1.0', 'UTF-8');
        $baseXML->formatOutput = true;
        $peakflowNode = $baseXML->createElement('peakflow');
        $peakflowNode->setAttribute('version', '2.0');
        $baseXML->appendChild($peakflowNode);

        return $baseXML;
    }

    /**
     * Get a Dom Element for use in the Query XML.
     *
     * @param array  $filter the filter array to build the filter node for the XML
     * @param object $xmlDOM the DOMDocument object
     *
     * @return object the DOM element to include in the query XML
     */
    private function addQueryFilter($filter, $xmlDOM)
    {
        $filterNode = $xmlDOM->createElement('filter');
        $filterNode->setAttribute('type', $filter['type']);

        if (true === $filter['binby']) {
            $filterNode->setAttribute('binby', 1);
        }

        if (null !== $filter['value']) {
            $instanceNode = $xmlDOM->createElement('instance');
            $instanceNode->setAttribute('value', $filter['value']);
            $filterNode->appendChild($instanceNode);
        }

        return $filterNode;
    }

    /**
     * Find an error in the results of the REST API which gave an Error.
     *
     * @param array $errors an array of errors returned by the API
     */
    private function findError($errors)
    {
        foreach ($errors as $error) {
            if (isset($error['id'])) {
                $this->errorMessage .= $error['id']."\n ";
            }
            if (isset($error['message'])) {
                $this->errorMessage .= $error['message']."\n ";
            }
            if (isset($error['title'])) {
                $this->errorMessage .= $error['title']."\n ";
            }
            if (isset($error['detail'])) {
                $this->errorMessage .= $error['detail']."\n ";
            }
        }
    }
}
