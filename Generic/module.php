<?php

//Tuya Klassen einbinden
//include "/../TuyaAPI.php";

class TuyaGeneric extends IPSModule
{
    // erstellung
    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterPropertyString("DeviceID", "");
        $this->RegisterPropertyString("DeviceName", "");
        $this->RegisterPropertyString("LocalKey", "");

        // modulaufruf ändern
        $Module = $this->GetBuffer("Module");
        if ($Module == "") {
            // default this Module
            $Module = json_decode(
                file_get_contents(__DIR__ . "/module.json"),
                true
            )["prefix"]; // Modul für parent merken
            $this->SetBuffer("Module", $Module);
        }
        $this->RegisterTimer(
            "SearchTime",
            0,
            $Module . "_TimerEvent(\$_IPS['TARGET']);"
        );

        // Connect to available gateway
        // $this->ConnectParent ( "{A52FEFE9-7858-4B8E-A96E-26E15CB944F7}" );
    }

    // changes der instanz
    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        // data filter actual not used
        //$this->SetReceiveDataFilter(".*\"DeviceID\":".$this->GetID().".*");
        //$this->SetReceiveDataFilter(".*\"DeviceID\":".(int)hexdec($this->ReadPropertyString("DeviceID")).".*");
    }

    /*
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     *
     * IOT_Send($id, $text);
     *
     * public function Send($Text)
     * {
     * $this->SendDataToParent(json_encode(Array("DataID" => "{B87AC955-F258-468B-92FE-F4E0866A9E18}", "Buffer" => $Text)));
     * }
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->SendDebug("TuyaGatewayData", $JSONString, 0);
    }

    // analyze recieved data
    private function ProcessData($data)
    {
        // nothing to do
    }

    // default debug message
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_array($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . "." . $Key, $DebugData, 0);
            }
        } else {
            parent::SendDebug($Message, $Data, $Format);
        }
    }

    // start/stop search device
    public function SearchModules(string $state)
    {
        if ($state == "true") {
            $this->SetBuffer("Serach", "true");
            $this->SetReceiveDataFilter("");
            $this->UpdateFormField("Actors", "values", "");
            // Timer starten für zeitlich begrenzte Suche
            $this->SetTimerInterval("SearchTime", 1000 * 60);
            $this->UpdateFormField("TimeLabel", "caption", "Suche läuft...");
        } else {
            $this->SetBuffer("Serach", "");
            $this->UpdateFormField("TimeLabel", "caption", "Suche abgelaufen");
            $this->SetTimerInterval("SearchTime", 0);
            $this->SetReceiveDataFilter(
                ".*\"DeviceID\":" . $this->GetID() . ".*"
            );
        }
    }

    // timer aufruf, geräte suche abgelaufen
    public function TimerEvent()
    {
        $this->SearchModules("false");
    }

    // auswahl aus der search liste
    public function SetSelectedModul(object $List)
    {
        @$DevID = $List["ID"]; // Kommt ein Error bei keiner Auswahl

        $this->SetBuffer("Serach", "");
        $this->SetBuffer("List", "");
        $this->SetTimerInterval("SearchTime", 0);

        if ($DevID != null) {
            IPS_SetProperty($this->InstanceID, "DeviceID", "" . $DevID);
        }
        // Apply schliesst auch popup
        IPS_ApplyChanges($this->InstanceID);
    }

    // ggf. auch entscheiden was in der Liste aufgenommen werden soll
    // z.b. Filter auf spezielle Geräte
    //
    public function updateList(string $DevID, object $data)
    {
        // Device Liste als Buffer
        $values = json_decode($this->GetBuffer("List")); //json_decode( $this );

        // fix 64 bit
        //$DevIDInt = (int)hexdec($DevID);
        //if($DevIDInt & 0x80000000)$DevIDInt -=  0x100000000;
        //$DevID = substr($DevID,8);

        $newValue = new stdClass();
        $newValue->ID = $DevID;
        $newValue->Ident = $DevID; //identifier hier gleich der device id

        // Add Info alle Daten anzeigen
        $AddInfo = strtoupper(
            str_pad(dechex($data->{'DataByte0'}), 2, 0, STR_PAD_LEFT)
        );
        $AddInfo =
            $AddInfo .
            "-" .
            strtoupper(
                str_pad(dechex($data->{'DataByte1'}), 2, 0, STR_PAD_LEFT)
            );
        $AddInfo =
            $AddInfo .
            "-" .
            strtoupper(
                str_pad(dechex($data->{'DataByte2'}), 2, 0, STR_PAD_LEFT)
            );
        $AddInfo =
            $AddInfo .
            "-" .
            strtoupper(
                str_pad(dechex($data->{'DataByte3'}), 2, 0, STR_PAD_LEFT)
            );
        $newValue->Reference = $AddInfo;

        if (
            @in_array($newValue->Ident, array_column($values, "Ident")) == false
        ) {
            $values[] = $newValue;

            $jsValues = json_encode($values);
            $this->SetBuffer("List", $jsValues);

            $this->UpdateFormField("Actors", "values", $jsValues);
        }
    }

    // merge von form.json
    public function AddConfigurationForm(array $ChildForm, string $NewModule)
    {
        // funktionsaufruf in form ändern ändern
        $Module = json_decode(
            file_get_contents(__DIR__ . "/module.json"),
            true
        );
        $NewModule = $this->GetBuffer("Module");

        $file = file_get_contents(__DIR__ . "/form.json");
        $file = str_replace($Module["prefix"] . "_", $NewModule . "_", $file);

        $Form = json_decode($file, true);

        // form merges
        if (array_key_exists("elements", $ChildForm) == false) {
            $ChildForm["elements"] = [];
        }
        if (array_key_exists("elements", $Form) == false) {
            $Form["elements"] = [];
        }
        if (array_key_exists("status", $ChildForm) == false) {
            $ChildForm["status"] = [];
        }
        if (array_key_exists("status", $Form) == false) {
            $Form["status"] = [];
        }
        if (array_key_exists("actions", $ChildForm) == false) {
            $ChildForm["actions"] = [];
        }
        if (array_key_exists("actions", $Form) == false) {
            $Form["actions"] = [];
        }

        // Arrays ersetzen
        $NewForm = [];
        $NewForm["elements"] = array_merge(
            $ChildForm["elements"],
            $Form["elements"]
        );
        $NewForm["status"] = array_merge($ChildForm["status"], $Form["status"]);
        $NewForm["actions"] = array_merge(
            $ChildForm["actions"],
            $Form["actions"]
        );

        return $NewForm;
    }
}

/**
 * Based on:
 * PHP TUYA API HOME MANAGEMENT CLIENT
 * PHP version 5.3+. 
 * @category 	Library
 * @version	1.0.0
 * @author   	Irony <irony00100@gmail.com>        https://github.com/ground-creative/tuyapiphp
 * Changes: Adding more API calls and copy functions in one file 
 */



class TuyaApi
{
    protected $_config =
        [
            'accessKey' => '',
            'secretKey' => '',
            'baseUrl' => '',
            'debug' => false,
            'associative' => false,
            'curl_http_version' => \CURL_HTTP_VERSION_1_1,
        ];

    protected $_required = ['accessKey', 'secretKey', 'baseUrl'];

    public function __construct($config)
    {
        $this->_checkConfig($config);
        $this->_config = array_merge($this->_config, $config);
    }

    public function devices($token)
    {
        return new Devices($this->_config, $token);
    }

    public function token()
    {
        return new Token($this->_config);
    }

    public function __get($name)
    {
        return $this->$name();
    }

    protected function _checkConfig($config)
    {
        try {
            if (count(array_intersect_key(array_flip($this->_required),
                $config)) !== count($this->_required)) {
                $msg = 'Please set "accessKey", "secretKey" and "baseUrl", aborting!';
                throw new \Exception($msg);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
}

class Token
{
    protected $_token = '';

    protected $_endpoints =
        [
            'get_new' => '/v1.0/token?grant_type=1',
            'get_refresh' => '/v1.0/token/{refresh_token}',
        ];

    public function __construct(protected array $_config)
    {
    }

    public function __call($name, $args = [])
    {
        $request = new Caller($this->_config, $this->_endpoints, $this->_token);

        return $request->send($name, $args);
    }
}

class Caller
{
    protected $_payload = [];

    protected $_sigHeaders = [];

    public function __construct(protected array $_config, protected $_endpoints, protected $_token = null)
    {
    }

    public function send($name, $args = [])
    {
        if (!array_key_exists($name, $this->_endpoints)) {
            try {
                throw new \Exception('Method "'.$name.'" is not supported!');
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
            exit;
        }
        $uri = $this->_endpoints[$name];
        preg_match('/put_|get_|post_|delete_/', (string) $name, $matches);
        $request = str_replace('_', '', $matches[0]);
        foreach ($args as $arg) {
            if (is_array($arg)) {
                if (empty($this->_payload)) {
                    $this->_payload = $arg;
                } else {
                    $this->_sigHeaders = $arg;
                }
            } else {
                $uri = preg_replace('/\{.*?\}/', (string) $arg, (string) $uri, 1);
            }
        }
        $request = new Request($this->_config, $uri, $request,
            $this->_token, $this->_payload, $this->_sigHeaders);

        return $request->call();
    }
}

class Request
{
    protected string|float $_time = '';

    protected string|array $_headers = '';

    protected string $_request = '';

    protected mixed $_token = '';

    protected string|false $_body = '';

    protected string|false $_payload = '';

    protected DebugHandler $_debug;

    public function __construct(protected array $_config, protected string $_endpoint, $request, $token = null, $payload = null, protected mixed $_sigHeaders = null)
    {
        $this->_time = round(microtime(true) * 1000);
        $this->_request = strtoupper((string) $request);
        $this->_token = $token ?: ''; // todo
        $this->_payload = $this->_setPayload($payload);
        $this->_body = ($payload && $this->_request != 'GET') ? json_encode($payload, JSON_THROW_ON_ERROR) : '';
        $string = [strtoupper((string) $request), hash('sha256', $this->_body), '', $this->_endpoint];
        $stringtosign = implode("\n", $string);
        $sign = $this->_sign($this->_time, $stringtosign);
        $this->_headers = $this->_headers($sign);
        $this->_debug = new DebugHandler($this->_config);
    }

    protected function _setPayload($payload)
    {
        if (!$payload) {
            return '';
        }
        if ($this->_request == 'POST' || $this->_request == 'PUT') {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } else {
            ksort($payload);
            /*$paramsJoined = [];
            foreach($payload as $param => $value)
            {
                $paramsJoined[] = "$param=$value";
            }
            $payload = implode('&', $paramsJoined);*/
            $payload = http_build_query($payload);
            $payload = str_replace('%2C', ',', $payload);	// fix comma url encoding
            $this->_endpoint = $this->_endpoint.((preg_match('#\?#',
                $this->_endpoint)) ? '&'.$payload : '?'.$payload);

            return '';
        }
    }

    public function call()
    {
        $this->_debug->output($this->_request.' '.$this->_config['baseUrl'].$this->_endpoint);
        $this->_debug->output('Headers:', $this->_headers);
        if ($this->_body) {
            $this->_debug->output('Payload:', json_decode(
                $this->_body, $this->_config['associative'], 512, JSON_THROW_ON_ERROR));
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_config['baseUrl'].$this->_endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_headers);
        // curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->_request);
        if ($this->_request == 'POST' || $this->_request == 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_body);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, $this->_config['curl_http_version']);
        
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->_debug->output('Curl error:', curl_error($ch));
        }
        //echo $result;
        $return = json_decode($result, $this->_config['associative'], 512, JSON_THROW_ON_ERROR);
        $this->_debug->output('Result:', $return);

        return $return;
    }

    protected function _sign($time, $stringToSign)
    {
        $sign = strtoupper(hash_hmac('sha256', $this->_config['accessKey'].
                $this->_token.$time.$stringToSign, (string) $this->_config['secretKey']));

        return $sign;
    }

    protected function _headers($sign)
    {
        $headers =
        [
            'Accept: application/json, text/plan, */*',
            't: '.$this->_time,
            'sign_method: HMAC-SHA256',
            'client_id: '.$this->_config['accessKey'],
            'sign: '.$sign,
            'User-Agent: tuyapiphp',
            'Signature-Headers: ', // todo
        ];
        if ($this->_request == 'POST' || $this->_request == 'PUT') {
            $headers[] = 'Content-Type: application/json';
        }
        if ($this->_token) {
            $headers[] = 'access_token: '.$this->_token;
        }

        return $headers;
    }
}

class DebugHandler
{
    public function __construct(protected array $_config)
    {
    }

    public function output($msg, $data = null)
    {
        if (@$this->_config['debug'] != true) {
            return;
        }
        if ($data) {
            echo $msg;
            echo '<pre>'.print_r($data, true).'</pre>';

            return;
        }
        echo $msg."<br>\n";
    }
}

class Devices
{
    protected $_endpoints =
    [
        'get_app_list' => '/v1.0/users/{appId}/devices',
        'get_list' => '/v1.0/devices',
        'get_user_list' => '/v1.0/users/{uid}/devices',
        'get_details' => '/v1.0/devices/{device_id}',
        'get_logs' => '/v1.0/devices/{device_id}/logs',
        'get_subdevices' => '/v1.0/devices/{deviceId}/sub-devices',
        'get_factory_info' => '/v1.0/devices/factory-infos',
        'get_user' => '/v1.0/devices/{device_id}/users/{user_id}',
        'get_users' => '/v1.0/devices/{device_id}/users',
        'get_category' => '/v1.0/functions/{category}',
        'get_functions' => '/v1.0/devices/{device_id}/functions',
        'get_specifications' => '/v1.0/devices/{device_id}/specifications',
        'get_status' => '/v1.0/devices/{device_id}/status',
        'get_multiple_names' => '/v1.0/devices/{device_id}/multiple-names',
        'get_groups' => '/v1.0/device-groups',
        'get_group' => '/v1.0/device-groups/{group_id}',
        'get_user_groups' => '/v1.0/users/{uid}/device-groups',
        'get_remote_unlocks' => '/v1.0/devices/{device_id}/door-lock/remote-unlocks',
        'get_openlogs' => '/v1.0/devices/{device_id}/door-lock/open-logs',
        'put_function_code' => '/v1.0/devices/{device_id}/functions/{function_code}',
        'put_reset_factory' => '/v1.0/devices/{device_id}/reset-factory',
        'put_name' => '/v1.0/devices/{device_id}',
        'put_user' => '/v1.0/devices/{device_id}/users/{user_id}',
        'put_multiple_names' => '/v1.0/devices/{device_id}/multiple-name',
        'put_group' => '/v1.0/device-groups/{group_id}',
        'put_enable_gateway' => '/v1.0/devices/{device_id}/enabled-sub-discovery',
        'post_commands' => '/v1.0/devices/{device_id}/commands',
        'post_user' => '/v1.0/devices/{device_id}/user',
        'post_group' => '/v1.0/device-groups',
        'post_group_issued' => '/v1.0/device-groups/{device_group_id}/issued',
        'post_stream_allocate' => '/v1.0/users/{uid}/devices/{device_id}/stream/actions/allocate',
        'post_remote_unlocking' => '/v1.0/devices/{device_id}/door-lock/password-free/open-door',
        'post_password_ticket' => '/v1.0/smart-lock/devices/{device_id}/password-ticket',
        'delete_device' => '/v1.0/devices/{device_id}',
        'delete_user' => '/v1.0/devices/{device_id}/users/{user_id}',
        'delete_group' => '/v1.0/device-groups/{group_id}',
        'get_test' => '/tylink/${deviceId}/device/sub/login',

    ];

    public function __construct(protected array $_config, protected $_token)
    {
    }

    public function __call($name, $args = [])
    {
        $request = new Caller($this->_config, $this->_endpoints, $this->_token);

        return $request->send($name, $args);
    }
}

?>
