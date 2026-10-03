<?php
/**
 * Minimal stand-ins for the parts of the Blesta framework used by the module.
 *
 * They mimic the behaviour of the real classes closely enough to exercise the module
 * (validation semantics follow minphp/input, views are actually rendered).
 */

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('ROOTWEBDIR')) {
    define('ROOTWEBDIR', dirname(__DIR__, 2) . DS);
}
if (!defined('CACHEDIR')) {
    define('CACHEDIR', sys_get_temp_dir() . DS);
}

/**
 * Shared test state (models, module rows, cache)
 */
class TestRegistry
{
    /** @var array Models injected by Loader::loadModels() */
    public static $models = [];

    /** @var array Module rows returned by Module::getModuleRows() */
    public static $rows = [];

    /** @var array Cache entries */
    public static $cache = [];

    public static function reset()
    {
        self::$models = [];
        self::$rows = [];
        self::$cache = [];
        Configure::set('Caching.on', false);
        Configure::set('Blesta.company_id', 1);
        Configure::set('Blesta.cache_length', '24 hours');
    }
}

class Language
{
    /** @var array Loaded language definitions */
    public static $lang = [];

    /** @var string The language used by the tests */
    public static $language = 'en_us';

    public static function loadLang($file, $language = null, $dir = '')
    {
        $path = rtrim($dir, DS) . DS . ($language ?? self::$language) . DS . (is_array($file) ? reset($file) : $file) . '.php';
        if (file_exists($path)) {
            $lang = [];
            include $path;
            self::$lang = array_merge(self::$lang, $lang);
        }
    }

    public static function _($key, $return = false, ...$args)
    {
        $value = self::$lang[$key] ?? $key;
        if (!empty($args)) {
            $value = vsprintf($value, $args);
        }

        if ($return) {
            return $value;
        }
        echo $value;
    }
}

class Configure
{
    private static $data = [];

    public static function set($key, $value)
    {
        self::$data[$key] = $value;
    }

    public static function get($key)
    {
        return self::$data[$key] ?? null;
    }

    public static function load($file, $dir = '')
    {
        $path = rtrim($dir, DS) . DS . $file . '.php';
        if (file_exists($path)) {
            include $path;
        }
    }
}

class Loader
{
    public static function loadComponents($object, array $components)
    {
        foreach ($components as $component) {
            if ($component == 'Input' && !isset($object->Input)) {
                $object->Input = new Input();
            }
        }
    }

    public static function loadModels($object, array $models)
    {
        foreach ($models as $model) {
            if (isset(TestRegistry::$models[$model])) {
                $object->{$model} = TestRegistry::$models[$model];
            }
        }
    }

    public static function loadHelpers($object, array $helpers)
    {
        // Helpers are attached to views by the View stub
    }

    public static function load($file)
    {
        require_once $file;
    }

    public static function toCamelCase($str)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}

class Cache
{
    public static function fetchCache($name, $path = '')
    {
        return TestRegistry::$cache[$path . $name] ?? false;
    }

    public static function writeCache($name, $data, $ttl, $path = '')
    {
        TestRegistry::$cache[$path . $name] = $data;
    }

    public static function clearCache($name, $path = '')
    {
        unset(TestRegistry::$cache[$path . $name]);
    }
}

/**
 * Validation with the semantics of minphp/input
 */
class Input
{
    private $rules = [];
    private $errors = [];
    private $end_checks = false;

    public function setRules(array $rules)
    {
        $this->rules = $rules;
        $this->errors = [];
    }

    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    public function errors()
    {
        return empty($this->errors) ? false : $this->errors;
    }

    public function validates(&$data)
    {
        $this->end_checks = false;
        foreach ($this->rules as $index => $rule) {
            $value = $data[$index] ?? null;
            $this->validateRule($index, $rule, $value);
            if ($this->end_checks) {
                break;
            }
        }

        return empty($this->errors);
    }

    public function isEmpty($value)
    {
        return trim((string) $value) === '';
    }

    public function matches($value, $pattern)
    {
        return (bool) preg_match($pattern, (string) $value);
    }

    private function validateRule($index, $rule, $value)
    {
        if (isset($rule['rule'])) {
            $rule = [$rule];
        }

        foreach ($rule as $type => $rule_set) {
            if (!isset($value) && !empty($rule_set['if_set'])) {
                continue;
            }

            $params = [];
            if (is_array($rule_set['rule'])) {
                $params = $rule_set['rule'];
                $method = array_shift($params);
            } else {
                $method = $rule_set['rule'];
            }
            array_unshift($params, $value);

            if (is_string($method) && method_exists($this, $method)) {
                $method = [$this, $method];
            }

            $failed = is_bool($method) ? !$method : !call_user_func_array($method, $params);
            if ((!empty($rule_set['negate']) && !$failed) || (empty($rule_set['negate']) && $failed)) {
                $this->errors[$index][$type] = $rule_set['message'] ?? null;
                if (!empty($rule_set['last'])) {
                    break;
                }
                if (!empty($rule_set['final'])) {
                    $this->end_checks = true;
                    break;
                }
            }
        }
    }
}

class ModuleField
{
    public $type;
    public $name;
    public $value;
    public $params;
    public $fields = [];

    public function __construct($type, $name = null, $value = null, array $params = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->value = $value;
        $this->params = $params;
    }

    public function attach(ModuleField $field)
    {
        $this->fields[] = $field;
    }
}

class ModuleFields
{
    private $fields = [];
    private $html = '';

    public function label($name, $for = null, array $attributes = [], $preserve_tags = false)
    {
        return new ModuleField('label', $for, $name, $attributes);
    }

    public function tooltip($message)
    {
        return new ModuleField('tooltip', null, $message);
    }

    public function __call($method, $args)
    {
        if (strpos($method, 'field') === 0) {
            return new ModuleField(lcfirst(substr($method, 5)), $args[0] ?? null, $args[1] ?? null, array_slice($args, 2));
        }

        throw new BadMethodCallException($method);
    }

    public function setField(ModuleField $field)
    {
        $this->fields[] = $field;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function setHtml($html)
    {
        $this->html = $html;
    }

    public function getHtml()
    {
        return $this->html;
    }

    /**
     * @return array All input field names (recursively)
     */
    public function getFieldNames()
    {
        $names = [];
        $walk = function ($fields) use (&$walk, &$names) {
            foreach ($fields as $field) {
                if ($field->type !== 'label' && $field->type !== 'tooltip' && $field->name !== null) {
                    $names[] = $field->name;
                }
                $walk($field->fields);
            }
        };
        $walk($this->fields);

        return array_values(array_unique($names));
    }
}

/**
 * HTML helper stub
 */
class HtmlHelperStub
{
    public function safe($value, $preserve_tags = false)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Form helper stub rendering minimal HTML
 */
class FormHelperStub
{
    private function attrs(array $attributes)
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return $html;
    }

    private function out($html, $return)
    {
        if ($return) {
            return $html;
        }
        echo $html;
    }

    public function create($action = null, array $attributes = [], $return = false)
    {
        return $this->out('<form method="post"' . ($action ? ' action="' . $action . '"' : '') . $this->attrs($attributes) . '>', $return);
    }

    public function end($attributes = [], $return = false)
    {
        return $this->out('</form>', $return);
    }

    public function label($name, $for = null, array $attributes = [], $preserve_tags = false, $return = false)
    {
        return $this->out('<label for="' . $for . '"' . $this->attrs($attributes) . '>' . htmlspecialchars((string) $name) . '</label>', $return);
    }

    public function fieldHidden($name, $value = null, array $attributes = [], $return = false)
    {
        return $this->out('<input type="hidden" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $this->attrs($attributes) . ' />', $return);
    }

    public function fieldText($name, $value = null, array $attributes = [], $return = false)
    {
        return $this->out('<input type="text" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $this->attrs($attributes) . ' />', $return);
    }

    public function fieldPassword($name, array $attributes = [], $return = false)
    {
        return $this->out('<input type="password" name="' . $name . '"' . $this->attrs($attributes) . ' />', $return);
    }

    public function fieldTextarea($name, $value = null, array $attributes = [], $return = false)
    {
        return $this->out('<textarea name="' . $name . '"' . $this->attrs($attributes) . '>' . htmlspecialchars((string) $value) . '</textarea>', $return);
    }

    public function fieldSelect($name, $options = [], $selected = null, array $attributes = [], array $option_attributes = [], $return = false)
    {
        $html = '<select name="' . $name . '"' . $this->attrs($attributes) . '>';
        foreach ((array) $options as $value => $label) {
            $html .= '<option value="' . htmlspecialchars((string) $value) . '"' . ((string) $value === (string) $selected ? ' selected="selected"' : '') . '>' . htmlspecialchars((string) $label) . '</option>';
        }

        return $this->out($html . '</select>', $return);
    }

    public function fieldCheckbox($name, $value = null, $checked = false, array $attributes = [], $return = false)
    {
        return $this->out('<input type="checkbox" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . ($checked ? ' checked="checked"' : '') . $this->attrs($attributes) . ' />', $return);
    }

    public function fieldRadio($name, $value = null, $checked = false, array $attributes = [], $return = false)
    {
        return $this->out('<input type="radio" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . ($checked ? ' checked="checked"' : '') . $this->attrs($attributes) . ' />', $return);
    }

    public function fieldSubmit($name, $value = null, array $attributes = [], $return = false)
    {
        return $this->out('<input type="submit" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $this->attrs($attributes) . ' />', $return);
    }

    public function fieldButton($name, $value = null, array $attributes = [], $return = false)
    {
        return $this->out('<button name="' . $name . '"' . $this->attrs($attributes) . '>' . htmlspecialchars((string) $value) . '</button>', $return);
    }

    public function collapseObjectArray($array, $value_key, $key = null)
    {
        $result = [];
        foreach ((array) $array as $object) {
            $result[$key === null ? count($result) : $object->{$key}] = $object->{$value_key};
        }

        return $result;
    }
}

/**
 * Widget helper stub
 */
class WidgetHelperStub
{
    public function clear()
    {
    }

    public function setLinkButtons(array $buttons)
    {
    }

    public function create($title = null, array $attributes = [])
    {
        echo '<div class="widget">' . $title;
    }

    public function end()
    {
        echo '</div>';
    }
}

/**
 * View stub that renders the module's .pdt templates
 */
#[\AllowDynamicProperties]
class View
{
    /** @var array All views rendered during a test, keyed by file name */
    public static $rendered = [];

    public $file;
    public $base_uri = '/admin/';
    public $vars = [];
    public $Form;
    public $Html;
    public $Widget;
    private $default_view = '';

    public function __construct($file = null, $type = 'default')
    {
        $this->file = $file;
        $this->Form = new FormHelperStub();
        $this->Html = new HtmlHelperStub();
        $this->Widget = new WidgetHelperStub();
    }

    public function setDefaultView($path)
    {
        $this->default_view = $path;
    }

    public function set($name, $value = null)
    {
        $this->vars[$name] = $value;
    }

    public function _($key, $return = false, ...$args)
    {
        return Language::_($key, $return, ...$args);
    }

    public function fetch($file = null)
    {
        $file = $file ?? $this->file;
        $path = ROOTWEBDIR . $this->default_view . 'views' . DS . 'default' . DS . $file . '.pdt';
        if (!file_exists($path)) {
            throw new RuntimeException('View not found: ' . $path);
        }

        extract($this->vars);
        ob_start();
        include $path;
        $html = ob_get_clean();

        self::$rendered[$file] = ['vars' => $this->vars, 'html' => $html];

        return $html;
    }
}

/**
 * Base module
 */
#[\AllowDynamicProperties]
abstract class Module
{
    protected $module;
    protected $module_row;
    protected $config;
    protected $messages = [];
    public $base_uri = '/admin/';
    public $view;

    /** @var array Log entries written by the module */
    public $logs = [];

    protected function loadConfig($file)
    {
        if (file_exists($file)) {
            $this->config = json_decode(file_get_contents($file));
        }
    }

    public function getName()
    {
        return Language::_($this->config->name ?? '', true);
    }

    public function getVersion()
    {
        return $this->config->version ?? null;
    }

    public function getType()
    {
        return $this->config->type ?? 'generic';
    }

    final public function setModule($module)
    {
        $this->module = $module;
    }

    final public function setModuleRow($module_row)
    {
        $this->module_row = $module_row;
    }

    final public function getModule()
    {
        return $this->module;
    }

    final public function getModuleRow($module_row_id = null)
    {
        if ($module_row_id) {
            foreach (TestRegistry::$rows as $row) {
                if ($row->id == $module_row_id) {
                    return $row;
                }
            }

            return false;
        }

        return $this->module_row;
    }

    final public function getModuleRows($module_group_id = null)
    {
        if (!isset($this->module->id)) {
            return false;
        }

        return array_values(TestRegistry::$rows);
    }

    public function getServiceName($service)
    {
        return null;
    }

    public function errors()
    {
        return isset($this->Input) ? $this->Input->errors() : false;
    }

    final protected function setMessage($type, $message)
    {
        $this->messages[$type]['message'][] = $message;
    }

    final public function getMessages()
    {
        return $this->messages;
    }

    protected function log($url, $data = null, $direction = 'input', $success = false)
    {
        $this->logs[] = compact('url', 'data', 'direction', 'success');

        return 'testgrp1';
    }

    protected function serviceFieldsToObject(array $fields)
    {
        $data = new stdClass();
        foreach ($fields as $field) {
            if (is_array($field)) {
                $data->{$field['key']} = $field['value'];
            } else {
                $data->{$field->key} = $field->value;
            }
        }

        return $data;
    }

    protected function arrayToModuleFields($arr, ?ModuleFields $fields = null, $vars = null)
    {
        $fields = $fields ?? new ModuleFields();
        foreach ($arr as $name => $field) {
            $type = $field['type'] ?? 'text';
            $value = isset($vars->{$name}) ? $vars->{$name} : ($field['options'] ?? null);
            if ($type == 'hidden') {
                $fields->setField($fields->fieldHidden($name, is_array($value) ? null : $value));
                continue;
            }

            $label = $fields->label($field['label'] ?? null, $name . '_id');
            if ($type == 'select' || $type == 'radio' || $type == 'checkbox') {
                foreach ((array) ($field['options'] ?? []) as $key => $option) {
                    $label->attach($fields->{'field' . ucfirst($type)}($name, $key, isset($vars->{$name}) && $vars->{$name} == $key));
                }
            } else {
                $label->attach($fields->{'field' . ucfirst($type)}($name, $value));
            }
            $fields->setField($label);
        }

        return $fields;
    }

    protected function getCommonError($type)
    {
        return ['module' => [$type => 'Module error: ' . $type]];
    }
}

abstract class RegistrarModule extends Module
{
    public function supportsFeature($feature_name)
    {
        return !empty($this->config->features->{$feature_name});
    }

    public function getServiceDomain($service)
    {
        if (isset($service->fields)) {
            foreach ($service->fields as $service_field) {
                if ($service_field->key == 'domain') {
                    return $service_field->value;
                }
            }
        }

        return $this->getServiceName($service);
    }

    public function getDomainIsLocked($domain, $module_row_id = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));

        return false;
    }
}
