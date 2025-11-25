<?php
/**
 * Author: RGC Data LLC
 */

namespace fmCWP;
/**
 * Class fmCWP
 */
class fmCWP
{
    /* --- Connection --- */
    private $host;
    private $db;
    private $layout;
    private $user;
    private $password;

    /* --- Define another attributes --- */
    private $token;
    private $rowNumber;

    /* --- Default options --- */
    private $tokenExpireTime = 14;
    private $sessionName = "fm-api-token";
    private $logDir = __DIR__ . "/log/";
    private $logType = self::LOG_TYPE_NONE;
    private $allowInsecure = false;
    private $tokenStorage = self::TS_TRANSIENT;
    private $autorelogin = true;
    private $tokenFilePath = "";

    /* --- Define log const --- */
    const LOG_TYPE_DEBUG = "debug"; // All Messages
    const LOG_TYPE_ERRORS = "errors"; // Only Errors
    const LOG_TYPE_NONE = "none"; // none

    const LS_ERROR = "error";
    const LS_SUCCESS = "success";
    const LS_INFO = "info";
    const LS_WARNING = "warning";
    const LS_DEBUG = "debug"; // Newly added

    const TS_FILE = "file";
    const TS_SESSION = "session";
    const TS_TRANSIENT = "transient"; // Added for WordPress transient storage



    const ERROR_RESPONSE_CODE = [400, 401, 403, 404, 405, 415, 500];
    const ERROR_AUTH_RESPONSE_CODE = 401;

    /**
     * fmCWP constructor.
     * @param string $host
     * @param string $db
     * @param string $layout
     * @param string $user
     * @param string $password
     * @option array $options
     */


     public function __construct($host, $db, $layout, $user, $password, $options = null)
     {
         $this->host = $host; // Ensure host is initialized immediately
         $this->db = $db;
         $this->layout = $layout;
         $this->user = $user;
         $this->password = $password;
     
         if ($options !== null) {
             $this->setOptions($options);
         }
     
         // Set default token file path if using file storage and no path is provided
         if ($this->tokenStorage === self::TS_FILE && empty($this->tokenFilePath)) {
             $this->tokenFilePath = __DIR__ . '/tokens'; // Directory for token files
         }
     
         $this->setTimezone();
     }
     


    // *********************************************************************************************************************************

    private function setLogRowNumber()
    {
        $this->rowNumber = wp_rand(1000000, 9999999);
    }

     // *********************************************************************************************************************************
    
    /**
     * @param array $options
     */
    private function setOptions($options)
    {
        /* --- Log type --- */
        if (isset($options["logType"])) {
            $logType = $options["logType"];

            if (in_array($logType, [self::LOG_TYPE_DEBUG, self::LOG_TYPE_ERRORS, self::LOG_TYPE_NONE]) && !empty($logType)) {
                $this->logType = $logType;
            } else {
                $this->response(-101);
            }
        }

        /* --- Log dir --- */
        if (isset($options["logDir"])) {
            $logDir = $options["logDir"];

            if (is_string($logDir) && !empty($logDir)) {
                $this->logDir = $logDir;
            } else {
                $this->response(-102);
            }
        }

        /* --- Session name --- */
        if (isset($options["sessionName"])) {
            $sessionName = $options["sessionName"];

            if (is_string($sessionName) && !empty($sessionName)) {
                $this->sessionName = $sessionName;
            } else {
                $this->response(-103);
            }
        }

        /* --- Token Expire Time ( In minutes ) --- */
        if (isset($options["tokenExpireTime"])) {
            $tokenExpireTime = $options["tokenExpireTime"];

            if (is_numeric($tokenExpireTime)) {
                $this->tokenExpireTime = $tokenExpireTime;
            } else {
                $this->response(-104);
            }
        }

        /* --- Allow Insecure --- */
        if (isset($options["allowInsecure"])) {
            $allowInsecure = $options["allowInsecure"];

            if (is_bool($allowInsecure)) {
                $this->allowInsecure = $allowInsecure;
            } else {
                $this->response(-105);
            }
        }


        /* --- Save FileMaker Token to --- */
        if (isset($options["tokenStorage"])) {
            $tokenStorage = $options["tokenStorage"];

            if (in_array($tokenStorage, [self::TS_FILE, self::TS_SESSION, self::TS_TRANSIENT])) {
                if($tokenStorage === self::TS_FILE){
                    if (isset($options["tokenFilePath"]) && !empty($options["tokenFilePath"]) && is_string($options["tokenFilePath"])) {
                        $this->tokenFilePath = $options["tokenFilePath"];
                    } else {
                        $this->response(-111);
                    }
                }
                $this->tokenStorage = $tokenStorage;
            } else {
                $this->response(-110);
            }
        }
    }


    // *********************************************************************************************************************************

    /**
     * Check if is set default timezone in PHP.ini
     */
    private function setTimezone()
    {
        // Intentionally left blank: do not change PHP's default timezone here.
        // WordPress exposes timezone helpers (current_time(), wp_timezone()) that should be used
        // when converting or formatting dates. Changing the global PHP timezone at runtime
        // is discouraged and flagged by PHPCS/PluginCheck.
    }

    // *********************************************************************************************************************************

    /**
     * @return mixed
     */
    public function logout()
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to logout from database"
        ));

        if ($this->isLogged() === false) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "User is logged out - invalid token"
            ));

            $this->destroySessionToken();
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/sessions/" . rawurlencode($this->token),
            "method" => "DELETE",
        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Logout was successfull",
                "data" => $result
            ));

            $this->destroySessionToken();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Logout was not successfull",
                "data" => $result
            ));

            $this->destroySessionToken();
        }

        return $result;

    }

    // *********************************************************************************************************************************

    /**
     * @param string $scriptName
     * @param array $scriptPrameters
     * @return bool|mixed
     */
    public function runScript($scriptName, $scriptParameters = null)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to run a script
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to run a script',
            'data' => $scriptParameters
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/script/" . rawurlencode($scriptName);
    
        // Append script parameters if provided
        if ($scriptParameters !== null) {
            $url .= '?' . http_build_query($scriptParameters);
        }
    
        // Call the centralized API handler
        return $this->callAPI('GET', $url);
    }
    
    // *********************************************************************************************************************************

    /**
     * @return bool|mixed
     */
    public function getDatabaseNames($relogin = false)
    {
        if (empty($this->host) || empty($this->user) || empty($this->password)) {
            return [
                'messages' => [
                    [
                        'code' => '-1',
                        'message' => 'The FileMaker connection settings are incomplete. Please check host, username, and password.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to get database names
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to get metadata - database names'
        ]);
    
        // Build the request URL
        $url = "/fmi/data/vLatest/databases";
    
        // Use Basic Authentication for this endpoint
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->user . ':' . $this->password),
            'Content-Type' => 'application/json'
        ];
    
        // Prepare the request
        $request = [
            'url' => $url,
            'method' => 'GET',
            'headers' => $headers
        ];
    
        // Make the request
        $response = $this->callURL($request);
    
        // Log the raw response
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_DEBUG,
            'message' => 'Raw response from getDatabaseNames',
            'data' => $response
        ]);
    
        try {
            // Check for errors
            $this->isError($response, true);
    
            $this->log([
                'line' => __LINE__,
                'method' => __METHOD__,
                'type' => self::LS_SUCCESS,
                'message' => 'Successfully retrieved database names',
                'data' => $response
            ]);
    
            return $response;
        } catch (\Exception $e) {
            $this->log([
                'line' => __LINE__,
                'method' => __METHOD__,
                'type' => self::LS_ERROR,
                'message' => 'Failed to retrieve database names',
                'data' => $response
            ]);
    
            // Retry with relogin if enabled
            if ($this->autorelogin && !$relogin) {
                return $this->getDatabaseNames(true);
            }
        }
    
        return $response;
    }
    

    
    // *********************************************************************************************************************************

    /**
     * @return bool|mixed
     */
    public function getProductInformation()
    {
        $this->setLogRowNumber();
    
        // Log the attempt to get product information
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to get metadata - product information'
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/vLatest/productInfo";
    
        // Call the centralized API handler
        return $this->callAPI('GET', $url);
    }
    
    // *********************************************************************************************************************************

    /**
     * @return bool|mixed
     */
    public function getScriptNames()
    {
        $this->setLogRowNumber();
    
        // Log the attempt to get script names
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to get script names'
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/scripts";
    
        // Call the centralized API handler
        return $this->callAPI('GET', $url);
    }
    
    // *********************************************************************************************************************************

    /**
     * @return bool|mixed
     */
    public function getLayoutNames()
    {
        $this->setLogRowNumber();
    
        // Log the attempt to get layout names
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to get layout names'
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts";
    
        // Call the centralized API handler
        return $this->callAPI('GET', $url);
    }
    
    // *********************************************************************************************************************************

    /**
     * @return bool|mixed
     */
    public function getLayoutMetadata()
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to get layout metadata
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to get layout metadata'
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout);
    
        // Call the centralized API handler
        return $this->callAPI('GET', $url);
    }
    
    // *********************************************************************************************************************************

    /**
     * @param array $parameters
     * @return bool|mixed
     */
    public function createRecord($parameters)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to create a record
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to create a record',
            'data' => $parameters
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records";
    
        // Call the centralized API handler, passing the parameters as the body
        return $this->callAPI('POST', $url, $parameters);
    }
    
    // *********************************************************************************************************************************

    /**
     * @param integer $id
     * @param array $parameters
     * @return bool|mixed
     */
    public function deleteRecord($id, $parameters = null)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to delete a record
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to delete a record',
            'data' => $parameters
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id;
    
        // Append parameters to the URL if provided
        if ($parameters !== null) {
            $url .= '?' . http_build_query($parameters);
        }
    
        // Call the centralized API handler
        return $this->callAPI('DELETE', $url);
    }
    
    // *********************************************************************************************************************************

    /**
     * @param integer $id
     * @param array $parameters
     * @return bool|mixed
     */
    public function duplicateRecord($id, $parameters = null)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to duplicate a record
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to duplicate a record',
            'data' => $parameters
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id . "/duplicate";
    
        // Call the centralized API handler with POST method
        return $this->callAPI('POST', $url, $parameters);
    }
    
    // *********************************************************************************************************************************

    /**
     * @param integer $id
     * @param array $parameters
     * @return bool|mixed
     */
    public function editRecord($id, $parameters)
    {
        $this->setLogRowNumber();
    
        // Log the attempt to edit a record
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to edit a record',
            'data' => $parameters
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id;
    
        // Call the centralized API handler with PATCH method
        return $this->callAPI('PATCH', $url, $parameters);
    }
    
    // *********************************************************************************************************************************

    /**
     * @param integer $id
     * @option array $parameters
     * @return bool|mixed
     */
    public function getRecord($id, $parameters = null)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to get a record
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to get a record from the database',
            'data' => $parameters
        ]);
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id;
    
        // Append query parameters if provided
        if ($parameters !== null) {
            $url .= "?" . http_build_query($parameters);
        }
    
        // Call the centralized API handler
        return $this->callAPI('GET', $url);
    }
    
    // *********************************************************************************************************************************
    
    /**
     * @option array $parameters
     * @return bool|mixed
     */
    public function getRecords($parameters = null)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();

        // Log the attempt to get records
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Attempt to get records',
            'data' => $parameters
        ]);

        if (isset($parameters['limit'])) {
            $parameters['_limit'] = $parameters['limit'];
            unset($parameters['limit']);
        }
        if (isset($parameters['offset'])) {
            $parameters['_offset'] = $parameters['offset'];
            unset($parameters['offset']);
        }

        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/";

        // Append query parameters if provided
        if ($parameters !== null) {
            $url .= '?' . http_build_query($parameters);
        }

        // Call the centralized API handler with GET method
        return $this->callAPI('GET', $url);
    }

    // *********************************************************************************************************************************    

    /**
     * @param array $parameters
     * @return bool|mixed
     */
    public function findRecords($parameters)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to find records
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to find records",
            "data" => $parameters
        ));
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/_find";
    
        // Call the centralized API handler
        return $this->callAPI('POST', $url, $parameters);
    }
    
    // *********************************************************************************************************************************

    /**
     * @param array $parameters
     * @return bool|mixed
     */
    public function setGlobalField($parameters)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Log the attempt to set global fields
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to set global fields",
            "data" => $parameters
        ));
    
        // Build the request URL
        $url = "https://" . $this->host . "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/globals/";
    
        // Call the centralized API handler
        return $this->callAPI('PATCH', $url, $parameters);
    }

    // *********************************************************************************************************************************

    /**
     * @param integer $id
     * @param string $containerFieldName
     * @param string containerFieldRepetition
     * @param array $file
     * @return bool|mixed
     */
    public function uploadFileToContainer($id, $containerFieldName, $containerFieldRepetition, $path)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        // Ensure that the file exists
        if (!file_exists($path)) {
            return [
                'status' => ['http_code' => 0],
                'result' => 'File not found at the provided path.'
            ];
        }
    
        // Log the file upload attempt
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to upload a file to a container",
            "data" => $path
        ));
    
        // Build the request URL
        $url = "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id . "/containers/" . $containerFieldName . "/" . $containerFieldRepetition;
    
        // Prepare file data for multipart/form-data upload
        $fileData = [
            'upload' => new \CURLFile($path, mime_content_type($path), basename($path))
        ];
    
        // Call the new file upload API handler
        return $this->callFileUploadAPI('POST', $url, $fileData);
    }
    
    
    
    // *********************************************************************************************************************************
    /**
     * @param integer $id
     * @param string $containerFieldName
     * @param string containerFieldRepetition
     * @param array $file
     * @return bool|mixed
     */
    public function uploadFormDataToContainer($id, $containerFieldName, $containerFieldRepetition, $file)
    {
        if (empty($this->layout)) {
            return [
                'messages' => [
                    [
                        'code' => '-2',
                        'message' => 'FileMaker layout is not set. Please specify a layout.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        $this->setLogRowNumber();
    
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to upload a form data file to a container",
            "data" => $file
        ));
    
        // Ensure the file exists
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return [
                'status' => ['http_code' => 0],
                'result' => 'File not found at the provided path.'
            ];
        }
    
        // Build the request URL
        $url = "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id . "/containers/" . $containerFieldName . "/" . $containerFieldRepetition;
    
        // Prepare file data for multipart/form-data upload
        $fileData = [
            'upload' => new \CURLFile($file['tmp_name'], mime_content_type($file['tmp_name']), $file['name'])
        ];
    
        // Call the new file upload API handler
        return $this->callFileUploadAPI('POST', $url, $fileData);
    }
    

    // *********************************************************************************************************************************

    /**
     * @param string $layout
     * @return bool
     */
    public function setFilemakerLayout($layout)
    {
        if (is_string($layout)) {
            $this->layout = $layout;
            return true;
        } else {
            return false;
        }
    }

    // *********************************************************************************************************************************

    /**
     * @param array $parameters
     * @return null|string
     */
    private function convertParametersToJson($parameters)
    {
        if (is_array($parameters)) {
            if (!empty($parameters)) {
                return wp_json_encode($parameters);
            } else {
                return null;
            }
        }
        return null;
    }

    // *********************************************************************************************************************************

    /**
     * @param array $parameters
     * @return string
     */
    private function convertParametersToString($parameters)
    {
        if (is_array($parameters)) {
            if (!empty($parameters)) {
                return http_build_query($parameters);
            } else {
                return "";
            }
        }
        return "";
    }

    // *********************************************************************************************************************************

    /**
     * @param array $requestSettings
     * @option array $data
     * @return mixed
     */
    private function callURL($requestSettings, $data = null)
    {
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Sending request using wp_remote_*"
        ));
    
        $headers = (isset($requestSettings["headers"]) ? $requestSettings["headers"] : []);
        $method = $requestSettings["method"];
        $url = "https://" . $this->host . $requestSettings["url"];
    
        // If data is provided and the method is not GET, ensure it's passed in the body as JSON
        $body = null;
        if ($data !== null && $method !== 'GET') {
            $body = wp_json_encode($data);
            $headers['Content-Type'] = 'application/json';  // Ensure the Content-Type is JSON
        }
    
        // Setup request arguments
        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'body'      => $body,
            'timeout'   => 100, // Adjust this based on your needs
            'sslverify' => !$this->allowInsecure // Handle SSL verification
        ];
    
        // Make the request using wp_remote_request for more flexibility
        $response = wp_remote_request($url, $args);
    
        // Check if the request failed
        if (is_wp_error($response)) {
            return [
                'status' => ['http_code' => 0], // No valid HTTP status
                'result' => $response->get_error_message()
            ];
        }
    
        // Get the HTTP response code
        $http_code = wp_remote_retrieve_response_code($response);
    
        // Decode the JSON response
        $body = wp_remote_retrieve_body($response);
        $decodedBody = json_decode($body, true);
    
        // Log the successful request
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Request sent successfully"
        ));
    
        return [
            'status' => ['http_code' => $http_code],
            'result' => ($decodedBody !== null ? $decodedBody : $body)
        ];
    }
    
    // *********************************************************************************************************************************
    
    private function getUserIdentifier() {
        if (isset($_COOKIE['fm_user_id'])) {
            return sanitize_text_field(wp_unslash($_COOKIE['fm_user_id']));
        }
        return null;
    }
    
    // *********************************************************************************************************************************

    /**
     * @return bool|mixed
     */
    public function login()
    {
        if (empty($this->host) || empty($this->db) || empty($this->user) || empty($this->password)) {
            return [
                'messages' => [
                    [
                        'code' => '-1',
                        'message' => 'The FileMaker connection settings are incomplete. Please check host, database, username, and password.'
                    ]
                ],
                'response' => new \stdClass()
            ];
        }
        // Cleanup old token files only if file storage is used
        if ($this->tokenStorage === self::TS_FILE) {
            $this->cleanupOldTokenFiles();
        }
    
        // Check for existing token for the current host
        $tokenProps = $this->getCurrentFileMakerTokenProps();
        if (!empty($tokenProps)) {
            // If the token exists, check if it's valid
            $currentHost = $this->host;
            if ($tokenProps['host'] !== $currentHost) {
                $this->destroySessionToken(); // Clear the token for the previous host
            }
        }
    
        // Prepare the request to obtain the token
        $request = [
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/sessions",
            "method" => "POST",
            "headers" => [
                "Authorization" => "Basic " . base64_encode($this->user . ":" . $this->password),
                "Content-Type"  => "application/json"
            ]
        ];
    
        // Perform the request
        $response = $this->callURL($request);
    
        // Check if the response contains an error
        if ($this->isError($response)) {
            return $response;
        }
    
        // Check if the response contains the token
        if (isset($response["result"]["response"]["token"])) {
            $this->token = $response["result"]["response"]["token"]; // Set the token
    
            // Calculate token expiration time
            $currentTime = new \DateTime();
            $expireTime = $currentTime->modify("+" . $this->tokenExpireTime . " minutes");
    
            // Store the token and expiration time to the file
            $this->setFileMakerTokenProps([
                "token" => $this->token,
                "expire" => $expireTime->format("Y-m-d H:i:s"),
                "host" => $this->host // Save the host for future reference
            ]);
    
            return true; // Return success
        } else {
            // Log failure to acquire the token
            $this->log([
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Token not found in response",
                "data" => $response
            ]);
    
            return [
                'status' => ['http_code' => 401],
                'result' => 'Token not found in response'
            ];
        }
    }
    

    
    // *********************************************************************************************************************************
    
    /**
     * @return bool
     */
    private function isLogged()
    {
        $this->log([
            'line' => __LINE__,
            'method' => __METHOD__,
            'type' => self::LS_INFO,
            'message' => 'Checking if user is logged into the database'
        ]);
    
        $tokenProps = $this->getCurrentFileMakerTokenProps();
    
        if (!empty($tokenProps)) {
            if ($tokenProps['host'] !== $this->host || $tokenProps['db'] !== $this->db) {
                $this->log([
                    'line' => __LINE__,
                    'method' => __METHOD__,
                    'type' => self::LS_WARNING,
                    'message' => 'Token belongs to a different host or database. Clearing it.'
                ]);
                $this->destroySessionToken(); // Clear mismatched token
                return false;
            }
    
            $currentTime = new \DateTime();
            $tokenExpire = \DateTime::createFromFormat('Y-m-d H:i:s', $tokenProps['expire']);
    
            if ($tokenExpire === false || $currentTime >= $tokenExpire) {
                $this->log([
                    'line' => __LINE__,
                    'method' => __METHOD__,
                    'type' => self::LS_WARNING,
                    'message' => 'Token is expired or invalid'
                ]);
                return false;
            }
    
            $this->setToken($tokenProps['token']);
            return true;
        }
    
        return false;
    }
    

    
    
    // *********************************************************************************************************************************

    private function extendTokenExpiration()
    {
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Token expiration time extending"
        ));

        $currentTime = new \DateTime();
        $tokenExpire = $currentTime->modify("+" . $this->tokenExpireTime . "minutes");
        $this->setFileMakerTokenProps(["expire" => $tokenExpire->format("Y-m-d H:i:s")]);
    }

    // *********************************************************************************************************************************

    private function getCurrentFileMakerTokenProps()
    {
        $data = null;
    
        // Generate unique token file name with host and database
        $userId = $this->getUserIdentifier();
        $hostHash = rawurlencode($this->host);
        $dbHash = rawurlencode($this->db);
        $filePath = $this->tokenFilePath . "/token_" . $hostHash . "_" . $dbHash . "_" . $userId . ".json";
    
        if ($this->tokenStorage === self::TS_FILE) {
            if (file_exists($filePath)) {
                global $wp_filesystem;
                WP_Filesystem(); // Ensure WP_Filesystem is initialized
    
                if ($wp_filesystem->exists($filePath)) {
                    $fileContents = $wp_filesystem->get_contents($filePath);
                    $dataRawArray = json_decode($fileContents, true);
    
                    if ($dataRawArray === null) {
                        $this->log([
                            "line" => __LINE__,
                            "method" => __METHOD__,
                            "type" => self::LS_ERROR,
                            "message" => "Failed to decode token file: " . $filePath
                        ]);
                    } else {
                        $data = $dataRawArray;
                    }
                } else {
                    $this->log([
                        "line" => __LINE__,
                        "method" => __METHOD__,
                        "type" => self::LS_ERROR,
                        "message" => "Token file does not exist: " . $filePath
                    ]);
                }
            } else {
                $this->log([
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_ERROR,
                    "message" => "Token file not found: " . $filePath
                ]);
            }
        } elseif ($this->tokenStorage === self::TS_SESSION) {
            if (isset($_SESSION[$this->sessionName])) {
                $dataRawArray = json_decode(sanitize_text_field(wp_unslash($_SESSION[$this->sessionName])), true);
                if (!empty($dataRawArray)) {
                    $data = $dataRawArray;
                }
            }
        } elseif ($this->tokenStorage === self::TS_TRANSIENT) {
            if (!function_exists('get_transient')) {
                $this->log([
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_ERROR,
                    "message" => "WordPress transient function (get_transient) not available. Cannot read from transient storage."
                ]);
                // If the function doesn't exist, we can't proceed with transient storage.
                // $data remains null as initialized.
            } else {
                $userId = $this->getUserIdentifier();
                if ($userId) {
                    $hostHash = rawurlencode($this->host);
                    $dbHash = rawurlencode($this->db);
                    $transientKey = "fm_api_token_" . $hostHash . "_" . $dbHash . "_" . $userId;
                    $transientData = get_transient($transientKey);

                    if ($transientData !== false) {
                        $dataRawArray = json_decode($transientData, true);
                        if (json_last_error() === JSON_ERROR_NONE && !empty($dataRawArray)) {
                            $data = $dataRawArray;
                        } else {
                            $this->log([
                                "line" => __LINE__,
                                "method" => __METHOD__,
                                "type" => self::LS_ERROR,
                                "message" => "Failed to decode token data from transient: " . $transientKey . " - JSON Error: " . json_last_error_msg()
                            ]);
                        }
                    }
                }
            }
        }
    
        return $data;
    }
    
    
    
    // *********************************************************************************************************************************

    private function setFileMakerTokenProps($data)
    {
        $this->initFilesystem();
        global $wp_filesystem;
    
        $currentData = $this->getCurrentFileMakerTokenProps();
    
        if (empty($currentData)) {
            $currentData = [
                "token" => "",
                "expire" => "",
                "host" => "",
                "db" => "" // Include database in token data
            ];
        }
    
        // Update data with provided values
        if (isset($data["token"])) {
            $currentData["token"] = $data["token"];
            $this->setToken($data["token"]);
        }
        if (isset($data["expire"])) {
            $currentData["expire"] = $data["expire"];
        }
        if (isset($data["host"])) {
            $currentData["host"] = $data["host"];
        } else {
            $currentData["host"] = $this->host;
        }
        if (isset($data["db"])) {
            $currentData["db"] = $data["db"];
        } else {
            $currentData["db"] = $this->db; // Ensure database is included
        }
    
        $dataJson = wp_json_encode($currentData);
    
        // Save token to file
        if ($this->tokenStorage === self::TS_FILE) {
            $userId = $this->getUserIdentifier();
            $hostHash = rawurlencode($this->host);
            $dbHash = rawurlencode($this->db);
            $filePath = $this->tokenFilePath . "/token_" . $hostHash . "_" . $dbHash . "_" . $userId . ".json";
    
            // Ensure directory exists
            if (!$wp_filesystem->is_dir($this->tokenFilePath)) {
                if (!$wp_filesystem->mkdir($this->tokenFilePath, FS_CHMOD_DIR)) {
                    $this->log([
                        "line" => __LINE__,
                        "method" => __METHOD__,
                        "type" => self::LS_ERROR,
                        "message" => "Failed to create token storage directory: " . $this->tokenFilePath
                    ]);
                    return;
                }
            }
    
            // Write the token to the file
            if (!$wp_filesystem->put_contents($filePath, $dataJson, FS_CHMOD_FILE)) {
                $this->log([
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_ERROR,
                    "message" => "Failed to write token to file: " . $filePath
                ]);
            } else {
                $this->log([
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_SUCCESS,
                    "message" => "Token successfully written to file: " . $filePath
                ]);
            }
        } elseif ($this->tokenStorage === self::TS_SESSION) {
        $_SESSION[$this->sessionName] = $dataJson;
    } elseif ($this->tokenStorage === self::TS_TRANSIENT) {
        if (!function_exists('set_transient') || !defined('MINUTE_IN_SECONDS')) {
            $this->log([
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "WordPress transient functions (set_transient) or constants (MINUTE_IN_SECONDS) not available. Cannot write to transient storage."
            ]);
            // If functions/constants aren't available, we can't proceed with transient storage.
            // The method will implicitly return at the end.
        } else {
            $userId = $this->getUserIdentifier();
            if ($userId) {
                $hostHash = rawurlencode($this->host);
                $dbHash = rawurlencode($this->db);
                $transientKey = "fm_api_token_" . $hostHash . "_" . $dbHash . "_" . $userId;
                // Use $this->tokenExpireTime (in minutes) for the transient expiration
                set_transient($transientKey, $dataJson, $this->tokenExpireTime * MINUTE_IN_SECONDS);
                $this->log([
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_SUCCESS,
                    "message" => "Token successfully written to transient: " . $transientKey
                ]);
            } else {
                $this->log([
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_ERROR,
                    "message" => "Failed to write token to transient: User identifier not found."
                ]);
            }
        }
        }
    }
    

    
    
    // *********************************************************************************************************************************

    private function setToken($token)
    {
        $this->token = $token;
    }

    private function destroySessionToken()
    {
        $this->initFilesystem();
        global $wp_filesystem;
    
        if (isset($_SESSION[$this->sessionName])) {
            unset($_SESSION[$this->sessionName]);
        }
    
        if ($this->tokenStorage === self::TS_FILE) {
            $userId = $this->getUserIdentifier();
            $hostHash = rawurlencode($this->host);
            $dbHash = rawurlencode($this->db);
            $filePath = $this->tokenFilePath . "/token_" . $hostHash . "_" . $dbHash . "_" . $userId . ".json";
    
            if ($wp_filesystem->exists($filePath)) {
                $wp_filesystem->delete($filePath);
                $this->log([
                    'line' => __LINE__,
                    'method' => __METHOD__,
                    'type' => self::LS_INFO,
                    'message' => "Deleted invalid token file: $filePath"
                ]);
            }
        }
    }
    
    
    // *********************************************************************************************************************************

    /**
     * @param array $log
     */
    private function log($log)
    {
        // Initialize WP_Filesystem
        $this->initFilesystem();
        global $wp_filesystem;
    
        $type = $log["type"] ?? '';
        $message = $log["message"] ?? '';
        $section = $log["method"] ?? '';
        $data = $log["data"] ?? '';
    
        if ($this->logType !== self::LOG_TYPE_NONE) {
            if ($this->logType == self::LOG_TYPE_ERRORS && $type === self::LS_ERROR || $this->logType == self::LOG_TYPE_DEBUG) {
    
                /* --- Define basic variable needed for log function --- */
                $log_message = "";
                $split_string = "\t";
    
                /* --- Row number --- */
                $log_message .= $this->rowNumber . $split_string;
    
                /* --- Date & Time --- */
                $log_message .= gmdate("Y-m-d H:i:s") . $split_string;
    
                /* --- Section name --- */
                $log_message .= !empty($section) ? $section . $split_string : "" . $split_string;
    
                /* --- Type --- */
                $log_message .= strtoupper($type) . $split_string;
    
                /* --- Data --- */
                $log_message .= !empty($data) ? wp_json_encode($data) . $split_string : "";
    
                /* --- Message --- */
                $log_message .= !empty($message) ? $message : "";
    
                $log_message .= "\n";
    
                /* --- Save log to file using WP_Filesystem --- */
                $pathDir = $this->logDir;
                $file = "fm-api-log_" . gmdate("d.m.Y") . ".txt";
    
                if ($wp_filesystem->is_dir($pathDir) && $wp_filesystem->is_writable($pathDir)) {
                    $wp_filesystem->put_contents($pathDir . $file, $log_message, FILE_APPEND);
                }
            }
        }
    }

    // *********************************************************************************************************************************

    private function initFilesystem() {
        if ( ! function_exists('WP_Filesystem') ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        WP_Filesystem();
        global $wp_filesystem;
    }

    // *********************************************************************************************************************************

    /**
     * @param integer $code
     */
    private function response($code)
    {
        echo esc_html( $code );
        exit();
    }

    // *********************************************************************************************************************************

    public function isError($result, $throwException = false)
    {
        // Also check for http_code === 0, which indicates an internal or connection error.
        if (isset($result['status']['http_code']) && (in_array($result['status']['http_code'], self::ERROR_RESPONSE_CODE) || $result['status']['http_code'] === 0)) {
            if ($throwException) {
                // Escape the JSON-encoded result for safe output in the exception message
                throw new \Exception( esc_html( wp_json_encode( $result['result'] ) ), intval( $result['status']['http_code'] ) );
            }
            return true;
        }
    
        // Check for FileMaker-specific errors
        if (isset($result['messages'][0]['code'])) {
            $errorCode = intval($result['messages'][0]['code']);
            if ($errorCode !== 0) {
                if ($throwException) {
                    // Escape the error message when thrown in an exception
                    throw new \Exception( esc_html( $result['messages'][0]['message'] ), intval( $errorCode ) );
                }
                return true;
            }
        }
    
        return false;
    }
    
    
    // *********************************************************************************************************************************
    
    public function isRecordExist($result){
        $recordMissingErrorCode = 401;

        if(isset($result["result"]["messages"][0]["code"]) && intval($result["result"]["messages"][0]["code"]) === $recordMissingErrorCode){
            return false;
        }
        return true;
    }

    // *********************************************************************************************************************************

    public function getResponse($result)
    {
        if (isset($result["result"])) {
            return $result["result"];
        }
    
        // If "result" key is not found, return the entire $result array or a default message
        return $result; 
    }


    // *********************************************************************************************************************************

    private function callAPI($method, $url, $body = null, $retry = false)
    {
        // Ensure the token is valid
        if ($this->isLogged() === false) {
            $login = $this->login();
            if ($login !== true) {
                return $login; // Return login error
            }
        }
    
        // Set headers including the Bearer token
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type'  => 'application/json'
        ];
    
        // Prepare request arguments
        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'body'      => $body ? wp_json_encode($body) : null,
            'timeout'   => 100, // Adjust timeout as needed
            'sslverify' => !$this->allowInsecure
        ];
    
        // Perform the request
        $response = wp_remote_request($url, $args);
    
        // Check if the request failed
        if (is_wp_error($response)) {
            return [
                'status' => ['http_code' => 0],
                'result' => $response->get_error_message()
            ];
        }
    
        // Decode the response body
        $result = json_decode(wp_remote_retrieve_body($response), true);
        $httpCode = wp_remote_retrieve_response_code($response);
    
        // Handle token invalidation (e.g., error 952 or HTTP 401)
        if ($httpCode === 401 || (isset($result['messages'][0]['code']) && intval($result['messages'][0]['code']) === 952)) {
            if ($retry) {
                // If already retried, fail the call
                return [
                    'status' => ['http_code' => $httpCode],
                    'result' => 'Token is invalid even after re-login.'
                ];
            }
    
            // Log the invalid token error
            $this->log([
                'line' => __LINE__,
                'method' => __METHOD__,
                'type' => self::LS_WARNING,
                'message' => 'Invalid token detected. Attempting to fetch a new token.'
            ]);
    
            // Destroy the invalid token
            $this->destroySessionToken();
    
            // Attempt to fetch a new token and retry the request
            $this->login();
            return $this->callAPI($method, $url, $body, true);
        }
    
        // Extend token expiration on success
        if ($httpCode === 200) {
            $this->extendTokenExpiration();
        }
    
        return [
            'status' => ['http_code' => $httpCode],
            'result' => $result
        ];
    }
    
    

    // *********************************************************************************************************************************

    private function callFileUploadAPI($method, $url, $fileData)
    {
        // Check if the token is valid or needs to be refreshed
        if ($this->isLogged() === false) {
            $login = $this->login();
            if ($login !== true) {
                return $login; // Return login error
            }
        }
    
        // Set authorization header with Bearer token
        $headers = [
            'Authorization' => 'Bearer ' . $this->token
        ];
    
        // Create a boundary for multipart form-data
        $boundary = wp_generate_password(24, false);
        $multipartBody = "";
    
        // Initialize WP_Filesystem for handling file uploads
        $this->initFilesystem();
        global $wp_filesystem;
    
        if (!WP_Filesystem()) {
            return [
                'status' => ['http_code' => 0],
                'result' => 'Failed to initialize WP_Filesystem.'
            ];
        }
    
        // Prepare multipart form-data body
        foreach ($fileData as $name => $content) {
            $multipartBody .= "--$boundary\r\n";
            
            // Handle if the content is a file
            if ($content instanceof \CURLFile) {
                $filename = $content->getPostFilename();
                $mimeType = $content->getMimeType();
                $filePath = $content->getFilename();
                
                // Read the file using WP_Filesystem
                if (!$wp_filesystem->exists($filePath)) {
                    return [
                        'status' => ['http_code' => 0],
                        'result' => 'File not found at the provided path: ' . $filePath
                    ];
                }
    
                $fileContents = $wp_filesystem->get_contents($filePath);
                if ($fileContents === false) {
                    return [
                        'status' => ['http_code' => 0],
                        'result' => 'Failed to read file contents using WP_Filesystem.'
                    ];
                }
    
                // Prepare the file section of the multipart body
                $multipartBody .= "Content-Disposition: form-data; name=\"$name\"; filename=\"$filename\"\r\n";
                $multipartBody .= "Content-Type: $mimeType\r\n\r\n";
                $multipartBody .= $fileContents . "\r\n";
            } else {
                // Regular field (non-file content)
                $multipartBody .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n";
                $multipartBody .= $content . "\r\n";
            }
        }
        $multipartBody .= "--$boundary--\r\n"; // End of body
    
        // Set headers for multipart request
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
    
        // Set up the request arguments
        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'body'      => $multipartBody,
            'timeout'   => 100, // Adjust this based on your needs
            'sslverify' => !$this->allowInsecure
        ];
    
        // Make the request using wp_remote_request
        $response = wp_remote_request("https://" . $this->host . $url, $args);
    
        // Check if the request failed
        if (is_wp_error($response)) {
            return [
                'status' => ['http_code' => 0],
                'result' => $response->get_error_message()
            ];
        }
    
        // Decode the response body
        $result = json_decode(wp_remote_retrieve_body($response), true);
    
        // Handle token invalidation (error 952)
        if (isset($result['messages'][0]['code']) && intval($result['messages'][0]['code']) === 952) {
            $this->destroySessionToken(); // Clear the invalid token
            $this->log([
                'line' => __LINE__,
                'method' => __METHOD__,
                'type' => self::LS_WARNING,
                'message' => 'Invalid token detected. Destroying token and attempting re-login.'
            ]);
    
            // Re-login and retry the request
            return $this->callFileUploadAPI($method, $url, $fileData, true);
        }
    
        // If the request is successful, extend the token expiration
        $this->extendTokenExpiration(); // Extend the token expiration on successful interaction
    
        return $result;
    }
    
    
    
// *********************************************************************************************************************************

    private function cleanupOldTokenFiles()
    {
        // Define the token expiration threshold (in seconds)
        $expirationThreshold = 60 * $this->tokenExpireTime;

        // Ensure the token storage directory exists
        $tokenDirectory = $this->tokenFilePath;
        if (!$tokenDirectory || !is_dir($tokenDirectory)) {
            return; // Exit if directory doesn't exist
        }

        // Initialize WP_Filesystem
        $this->initFilesystem();
        global $wp_filesystem;

        // List all files in the token directory
        $tokenFiles = $wp_filesystem->dirlist($tokenDirectory);

        if (!$tokenFiles || !is_array($tokenFiles)) {
            return; // No files to process
        }

        $currentTime = time();

        // Loop through all files in the directory
        foreach ($tokenFiles as $file) {
            // Skip non-token files (Optional: Add a more generic match if needed)
            if (!preg_match('/^token_.+\.json$/', $file['name'])) {
                continue;
            }

            $filePath = $tokenDirectory . '/' . $file['name'];

            // Ensure the file exists
            if (!$wp_filesystem->exists($filePath)) {
                continue;
            }

            // Get the file's modification time
            $fileModTime = $wp_filesystem->mtime($filePath);

            // Check if the file is older than the threshold
            if (($currentTime - $fileModTime) > $expirationThreshold) {
                // Attempt to delete the file
                if ($wp_filesystem->delete($filePath)) {
                    // Log the successful deletion
                    $this->log([
                        'line' => __LINE__,
                        'method' => __METHOD__,
                        'type' => self::LS_INFO,
                        'message' => "Deleted expired token file: $filePath"
                    ]);
                } else {
                    // Log the failure to delete
                    $this->log([
                        'line' => __LINE__,
                        'method' => __METHOD__,
                        'type' => self::LS_ERROR,
                        'message' => "Failed to delete expired token file: $filePath"
                    ]);
                }
            }
        }
    }



    
}