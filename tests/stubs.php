<?php

// Minimaler IP-Symcon-Ersatz, damit die Module ohne Symcon-Server testbar sind.
// Bildet nur ab, was die Module tatsaechlich nutzen.

class TestRegistry
{
    public static array $values = [];      // VariableID => Wert
    public static array $log = [];         // IPS_LogMessage
    public static array $sleeps = [];      // IPS_Sleep-Aufrufe
    public static array $parentConfig = [];
    public static int $nextID = 10000;
    public static int $runlevel = KR_READY;

    public static function reset(): void
    {
        self::$values = [];
        self::$log = [];
        self::$sleeps = [];
        self::$runlevel = KR_READY;
        self::$parentConfig = ['AccessKey' => 'a', 'SecretKey' => 's', 'BaseUrl' => 'https://example.invalid', 'AppID' => 'app', 'Interval' => 15];
    }
}

const KR_CREATE = 10101;
const KR_INIT = 10102;
const KR_READY = 10103;
const IPS_KERNELSTARTED = 10001;

class IPSModule
{
    public ?IPSModule $testParent = null;  // Testverdrahtung statt Symcon-Datenfluss
    public array $testChildren = [];
    public array $attributes = [];
    public string $receiveFilter = '';
    public array $messages = [];

    public int $InstanceID;
    public array $properties = [];
    public array $variables = [];      // Ident => VariableID
    public array $actions = [];
    public array $timers = [];         // Name => [Interval, Script]
    public array $status = [];
    public array $buffers = [];
    public array $debug = [];

    public function __construct($InstanceID)
    {
        $this->InstanceID = $InstanceID;
    }

    public function Create() {}
    public function ApplyChanges() {}
    public function RequestAction($Ident, $Value) {}

    protected function RegisterPropertyString($Name, $Default) { $this->properties[$Name] = $Default; }
    protected function RegisterPropertyInteger($Name, $Default) { $this->properties[$Name] = $Default; }
    protected function RegisterPropertyBoolean($Name, $Default) { $this->properties[$Name] = $Default; }
    protected function ReadPropertyString($Name) { return (string) $this->properties[$Name]; }
    protected function ReadPropertyInteger($Name) { return (int) $this->properties[$Name]; }
    protected function ReadPropertyBoolean($Name) { return (bool) $this->properties[$Name]; }

    protected function RegisterAttributeString($Name, $Default) { $this->attributes[$Name] ??= $Default; }
    protected function RegisterAttributeInteger($Name, $Default) { $this->attributes[$Name] ??= $Default; }
    protected function ReadAttributeString($Name) { return (string) $this->attributes[$Name]; }
    protected function ReadAttributeInteger($Name) { return (int) $this->attributes[$Name]; }
    protected function WriteAttributeString($Name, $Value) { $this->attributes[$Name] = $Value; }
    protected function WriteAttributeInteger($Name, $Value) { $this->attributes[$Name] = $Value; }

    protected function RegisterMessage($SenderID, $Message) { $this->messages[] = [$SenderID, $Message]; }
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {}
    protected function SetReceiveDataFilter($Filter) { $this->receiveFilter = $Filter; }
    protected function HasActiveParent() { return $this->testParent !== null && $this->testParent->currentStatus() === 102; }

    private function registerVariable($Ident, $Default)
    {
        if (!isset($this->variables[$Ident])) {
            $id = TestRegistry::$nextID++;
            $this->variables[$Ident] = $id;
            TestRegistry::$values[$id] = $Default;
        }
        return $this->variables[$Ident];
    }
    protected function RegisterVariableBoolean($Ident, $Name, $Profile = '', $Position = 0) { return $this->registerVariable($Ident, false); }
    protected function RegisterVariableInteger($Ident, $Name, $Profile = '', $Position = 0) { return $this->registerVariable($Ident, 0); }
    protected function RegisterVariableFloat($Ident, $Name, $Profile = '', $Position = 0) { return $this->registerVariable($Ident, 0.0); }
    protected function RegisterVariableString($Ident, $Name, $Profile = '', $Position = 0) { return $this->registerVariable($Ident, ''); }

    protected function EnableAction($Ident) { $this->actions[$Ident] = true; }
    protected function GetIDForIdent($Ident) { return $this->variables[$Ident] ?? false; }
    protected function SetValue($Ident, $Value) { TestRegistry::$values[$this->variables[$Ident]] = $Value; return true; }
    protected function GetValue($Ident) { return TestRegistry::$values[$this->variables[$Ident]]; }

    protected function RegisterTimer($Name, $Interval, $Script) { $this->timers[$Name] = [$Interval, $Script]; }
    protected function SetTimerInterval($Name, $Interval) { $this->timers[$Name][0] = $Interval; }
    protected function RegisterOnceTimer($Name, $Script) { $this->timers[$Name] = ['once', $Script]; }

    protected function ConnectParent($ModuleID) {}
    protected function RequireParent($ModuleID) {}
    protected function SetStatus($Status) { $this->status[] = $Status; }
    protected function GetStatus() { return $this->currentStatus(); }
    public function currentStatus() { return end($this->status) ?: 102; }
    protected function SetBuffer($Name, $Data) { $this->buffers[$Name] = $Data; }
    protected function GetBuffer($Name) { return $this->buffers[$Name] ?? ''; }
    protected function UpdateFormField($Field, $Parameter, $Value) {}
    protected function SendDebug($Message, $Data, $Format) { $this->debug[] = [$Message, $Data]; }
    protected function SendDataToParent($Data) { return $this->testParent ? $this->testParent->ForwardData($Data) : ''; }
    protected function SendDataToChildren($Data)
    {
        foreach ($this->testChildren as $child) {
            if ($child->receiveFilter === '' || preg_match('/' . $child->receiveFilter . '/', $Data)) {
                $child->ReceiveData($Data);
            }
        }
    }

    // Testhilfe
    public function value($Ident) { return TestRegistry::$values[$this->variables[$Ident]]; }
}

// Globale Symcon-Funktionen
function SetValue($VariableID, $Value) { TestRegistry::$values[$VariableID] = $Value; return true; }
function GetValue($VariableID) { return TestRegistry::$values[$VariableID]; }
function IPS_LogMessage($Sender, $Message) { TestRegistry::$log[] = "$Sender: $Message"; }
function IPS_GetKernelRunlevel() { return TestRegistry::$runlevel; }
function IPS_Sleep($ms) { TestRegistry::$sleeps[] = $ms; }
function IPS_SetIcon($ID, $Icon) {}
function IPS_GetInstance($ID) { return ['ConnectionID' => 1]; }
function IPS_GetConfiguration($ID) { return json_encode(TestRegistry::$parentConfig); }
function IPS_GetName($ID) { return 'Test'; }
function IPS_SetName($ID, $Name) {}
function IPS_SetProperty($ID, $Name, $Value) {}
function IPS_ApplyChanges($ID) {}
function IPS_VariableProfileExists($Name) { return true; }
function IPS_CreateVariableProfile($Name, $Type) {}
function IPS_SetVariableProfileText($Name, $Prefix, $Suffix) {}
function IPS_SetVariableProfileIcon($Name, $Icon) {}
function IPS_SetVariableProfileValues($Name, $Min, $Max, $Step) {}
function IPS_SetVariableProfileAssociation($Name, $Value, $Text, $Icon, $Color) {}
