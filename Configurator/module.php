<?php

include_once __DIR__ . "/../libs/TuyaDataFlow.php";

// Konfigurator: zeigt die Geraete des verknuepften Tuya-App-Kontos, legt fehlende Instanzen an
// und ordnet vorhandene Instanzen ueber ihre DeviceID zu
class TuyaConfigurator extends IPSModule
{
    use TuyaDataFlow;

    const IO_GUID = '{78ABC644-1134-F4E2-3E31-01E45483367B}';
    const MODULE_GENERIC = '{C490FACE-78CD-3AF7-918F-CC33DADD7F07}';
    const MODULE_SWITCH = '{EBCE6DBD-5213-E3B0-6DF1-0BC34504F3F9}';
    const MODULE_TH = '{C8CDF2A1-7FF8-6F38-FA32-1590EED383A7}';
    const MODULE_RGBW = '{5B9C0F92-91DA-0005-CB08-99844E8F2586}';
    const MODULE_LOCK = '{3A4F1BCD-C90E-0977-8E7B-6396455735B7}';

    // gerätemodule mit eigenschaft DeviceID und ihre anzeigenamen
    const DEVICE_MODULES = [
        self::MODULE_LOCK => 'Lock',
        self::MODULE_RGBW => 'Light',
        self::MODULE_TH => 'Temperature/humidity sensor',
        self::MODULE_SWITCH => 'Switch',
        self::MODULE_GENERIC => 'Generic',
    ];

    const GATEWAY_CATEGORIES = ['wg', 'wg2'];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent(self::IO_GUID);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // statuspakete des IO sind fuer den konfigurator ohne bedeutung
        $this->SetReceiveDataFilter('^$');
    }

    public function ReceiveData($JSONString)
    {
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $instances = $this->deviceInstances();
        $rows = [];
        $hint = null;

        if (!$this->HasActiveParent()) {
            $hint = $this->Translate("TuyaClient is not active, device list not available.");
        } else {
            try {
                foreach ($this->cloudDevices() as $device) {
                    $rows[] = $this->deviceRow($device, $instances);
                }
            } catch (TuyaApiException $e) {
                $hint = sprintf($this->Translate("Device list not available: %s"), $e->getMessage());
            }
        }

        // instanzen ohne geraet in der cloud, doppelte geraete-ids oder cloud nicht erreichbar
        foreach ($instances as $instanceID => $deviceID) {
            $moduleID = IPS_GetInstance($instanceID)['ModuleInfo']['ModuleID'] ?? '';
            $rows[] = [
                'Name' => IPS_GetName($instanceID),
                'Model' => '',
                'Module' => $this->Translate(self::DEVICE_MODULES[$moduleID] ?? 'Generic'),
                'Online' => '',
                'DeviceID' => $deviceID,
                'instanceID' => $instanceID,
            ];
        }

        foreach ($form['actions'] as &$element) {
            if ($element['type'] === 'Configurator') {
                $element['values'] = $rows;
            }
        }
        unset($element);
        if ($hint !== null) {
            array_unshift($form['actions'], ['type' => 'Label', 'caption' => $hint]);
        }
        return json_encode($form);
    }

    // geraete des verknuepften app-kontos ueber das IO
    private function cloudDevices(): array
    {
        $appID = $this->api('config')->AppID;
        $return = $this->api('get_app_list', $appID);
        if (empty($return->success) || !is_array($return->result ?? null)) {
            throw new TuyaApiException($return->msg ?? "invalid response");
        }
        return $return->result;
    }

    // vorhandene instanzen der geraetemodule mit nicht leerer DeviceID: [InstanzID => DeviceID], aufsteigend
    private function deviceInstances(): array
    {
        $instances = [];
        foreach (array_keys(self::DEVICE_MODULES) as $moduleID) {
            foreach (IPS_GetInstanceListByModuleID($moduleID) as $instanceID) {
                $deviceID = (string) IPS_GetProperty($instanceID, 'DeviceID');
                if ($deviceID !== '') {
                    $instances[$instanceID] = $deviceID;
                }
            }
        }
        ksort($instances);
        return $instances;
    }

    // zeile fuer ein cloud-geraet; die zugeordnete instanz wird aus $instances entfernt
    private function deviceRow($device, array &$instances): array
    {
        $instanceID = array_search($device->id, $instances, true);
        if ($instanceID !== false) {
            unset($instances[$instanceID]);
        }
        $module = $this->moduleFor($device);
        $name = ($device->name ?? '') !== '' ? $device->name : $device->id;

        $row = [
            'Name' => $name,
            'Model' => $device->model ?? '',
            'Module' => $module === null ? '–' : $this->Translate(self::DEVICE_MODULES[$module['moduleID']]),
            'Online' => $this->Translate(!empty($device->online) ? 'Yes' : 'No'),
            'DeviceID' => $device->id,
            'instanceID' => $instanceID === false ? 0 : $instanceID,
        ];
        if ($module !== null) {
            $row['create'] = [
                'moduleID' => $module['moduleID'],
                'configuration' => array_merge(['DeviceID' => $device->id, 'LocalKey' => $device->local_key ?? ''], $module['configuration']),
                'name' => $name,
                'location' => ['Tuya'],
            ];
        }
        return $row;
    }

    // passendes geraetemodul nach den datenpunkten des geraets, null fuer gateways
    private function moduleFor($device): ?array
    {
        $codes = array_column($device->status ?? [], 'code');
        $category = $device->category ?? '';

        if (in_array('lock_motor_state', $codes, true) || $category === 'ms') {
            return ['moduleID' => self::MODULE_LOCK, 'configuration' => []];
        }
        if (in_array('switch_led', $codes, true)) {
            $v2 = count(array_filter($codes, fn ($code) => str_ends_with($code, '_v2'))) > 0;
            return ['moduleID' => self::MODULE_RGBW, 'configuration' => ['Version' => $v2 ? '_v2' : '']];
        }
        if (in_array('va_temperature', $codes, true) || in_array('va_humidity', $codes, true)) {
            return ['moduleID' => self::MODULE_TH, 'configuration' => []];
        }
        if (in_array('switch_1', $codes, true)) {
            return ['moduleID' => self::MODULE_SWITCH, 'configuration' => []];
        }
        if (in_array($category, self::GATEWAY_CATEGORIES, true) || $codes === ['up_channel']) {
            return null;
        }
        return ['moduleID' => self::MODULE_GENERIC, 'configuration' => []];
    }
}
