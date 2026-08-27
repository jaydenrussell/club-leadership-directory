<?php
defined('JEXEC') or define('JEXEC', 1);
if (!defined('JPATH_ROOT')) define('JPATH_ROOT', dirname(__DIR__));
if (!defined('JPATH_ADMINISTRATOR')) define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');

// Minimal Joomla stubs so helpers/models load without full CMS
if (!class_exists('Joomla\CMS\Factory')) {
    eval('namespace Joomla\CMS; class Factory { public static function getUser(){ return new class { public function authorise($a,$b){return true;} public function getAuthorisedViewLevels(){return [1,2,3];} public $id=42; }; } public static function getDate(){ return new class { public function toSql(){ return date("Y-m-d H:i:s"); } }; } public static function getApplication(){ return new class { public $input; public function __construct(){ $this->input=new class{public function get($k,$d=null,$f="cmd"){return $d;} public function getInt($k,$d=0){return $d;} public function getMethod(){return "POST";} public function post(){return $this;} public function getArray(){return [];} }; } public function enqueueMessage($m,$t="message"){} public function close(){} }; } }');
}
if (!class_exists('Joomla\CMS\Language\Text')) {
    eval('namespace Joomla\CMS\Language; class Text { public static function _($k){return $k;} public static function sprintf($k,...$a){return $k;} }');
}
if (!class_exists('Joomla\CMS\HTML\HTMLHelper')) {
    eval('namespace Joomla\CMS\HTML; class HTMLHelper { public static function __callStatic($m,$a){return ""; } }');
}
if (!class_exists('Joomla\CMS\Log\Log')) {
    eval('namespace Joomla\CMS\Log; class Log { const WARNING=4; public static function add($m,$p,$c){} }');
}
if (!class_exists('Joomla\CMS\Router\Route')) {
    eval('namespace Joomla\CMS\Router; class Route { public static function _($u){return $u;} }');
}
if (!class_exists('Joomla\CMS\Object\CMSObject')) {
    eval('namespace Joomla\CMS\Object; class CMSObject { private $d=[]; public function set($k,$v){$this->d[$k]=$v;} public function get($k,$d=null){return $this->d[$k]??$d;} }');
}
