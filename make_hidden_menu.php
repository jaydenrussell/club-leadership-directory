<?php
/**
 * One-shot installer helper: creates a hidden menu + a Contact menu item
 * (alias "inquire") pointing at contact id 7 so the leadership directory's
 * vacant/contact links resolve to a clean SEF alias instead of
 * index.php?option=com_contact&view=contact&id=7.
 *
 * Run once from the Joomla root, then delete this file.
 */
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$app = \JFactory::getApplication('site');
$db  = \JFactory::getDbo();

// 1. Create the hidden menu if missing.
$menuType = 'hiddenmenu';
$exists = $db->setQuery(
    $db->getQuery(true)->select('COUNT(*)')->from('#__menu_types')->where('menutype=' . $db->q($menuType))
)->loadResult();
if (!$exists) {
    $db->setQuery(sprintf(
        "INSERT INTO #__menu_types (menutype, title, description) VALUES (%s, %s, %s)",
        $db->q($menuType), $db->q('Hidden Menu'), $db->q('Stealth contact aliases')
    ));
    $db->execute();
    echo "created menu hiddenmenu\n";
} else {
    echo "menu hiddenmenu exists\n";
}

// 2. Create the Contact item if missing.
$existsItem = $db->setQuery(
    $db->getQuery(true)->select('COUNT(*)')->from('#__menu')
        ->where('menutype=' . $db->q($menuType))
        ->where('link=' . $db->q('index.php?option=com_contact&view=contact&id=7'))
)->loadResult();
if (!$existsItem) {
    $ins = (object) array(
        'menutype'     => $menuType,
        'title'        => 'Inquire',
        'alias'        => 'inquire',
        'link'         => 'index.php?option=com_contact&view=contact&id=7',
        'type'         => 'component',
        'published'    => 1,
        'parent_id'    => 1,
        'level'        => 1,
        'component_id' => (int) $db->setQuery(
            $db->getQuery(true)->select('extension_id')->from('#__extensions')
                ->where('element=' . $db->q('com_contact'))->where('type=' . $db->q('component'))
        )->loadResult(),
        'browserNav'   => 0,
        'access'       => 1,
        'img'          => '',
        'client_id'    => 0,
        'home'         => 0,
    );
    $db->insertObject('#__menu', $ins);
    $id = $db->insertid();
    // Set lft/rgt and path via JTableMenu to keep nesting valid.
    require_once JPATH_LIBRARIES . '/joomla/table/menu.php';
    $table = \JTable::getInstance('Menu', 'JTable');
    if ($table->load($id)) {
        $table->setLocation(1, 'last-child');
        $table->store();
    }
    // Rebuild the menu path.
    $db->setQuery('UPDATE #__menu SET path=' . $db->q('inquire') . ' WHERE id=' . (int) $id);
    $db->execute();
    echo "created contact item id=$id alias=inquire\n";
} else {
    echo "contact item already exists\n";
}
echo "DONE\n";
