# -*- coding: utf-8 -*-
p = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\helpers.php"
s = open(p, encoding="utf-8").read()

old_method = (
    "    /**\n"
    "     * Resolve a Joomla Contact's email address (email_to). Used so a vacant\n"
    "     * position's CTA opens mailto: directly instead of the contact page.\n"
    "     *\n"
    "     * @param int $contactId\n"
    "     * @return string  email address, or '' if not found\n"
    "     */\n"
    "    public static function contactEmail($contactId)\n"
    "    {\n"
    "        $contactId = (int) $contactId;\n"
    "        if ($contactId <= 0) {\n"
    "            return '';\n"
    "        }\n"
    "        try {\n"
    "            $table = Table::getInstance('Contact', 'Joomla\\\\CMS\\\\Table\\\\');\n"
    "        } catch (\\Throwable $e) {\n"
    "            $table = null;\n"
    "        }\n"
    "        if ($table === null) {\n"
    "            return '';\n"
    "        }\n"
    "        if (!$table->load($contactId)) {\n"
    "            return '';\n"
    "        }\n"
    "        $email = isset($table->email_to) ? $table->email_to : '';\n"
    "        return trim((string) $email);\n"
    "    }\n"
    "}\n"
)
new_method = (
    "    /**\n"
    "     * Resolve a Joomla Contact's email address (email_to). Used so a vacant\n"
    "     * position's CTA opens mailto: directly instead of the contact page.\n"
    "     *\n"
    "     * @param int $contactId\n"
    "     * @return string  email address, or '' if not found\n"
    "     */\n"
    "    public static function contactEmail($contactId)\n"
    "    {\n"
    "        $contactId = (int) $contactId;\n"
    "        if ($contactId <= 0) {\n"
    "            return '';\n"
    "        }\n"
    "        try {\n"
    "            $db    = Factory::getDbo();\n"
    "            $query = $db->getQuery(true)\n"
    "                ->select($db->quoteName('email_to'))\n"
    "                ->from($db->quoteName('#__contact_details'))\n"
    "                ->where($db->quoteName('id') . ' = ' . (int) $contactId);\n"
    "            $db->setQuery($query);\n"
    "            $email = (string) $db->loadResult();\n"
    "        } catch (\\Throwable $e) {\n"
    "            $email = '';\n"
    "        }\n"
    "        return trim($email);\n"
    "    }\n"
    "}\n"
)
assert old_method in s, "contactEmail method not found"
s = s.replace(old_method, new_method, 1)

# The Table use statement is now unused; remove it to keep imports clean.
s = s.replace("use Joomla\\CMS\\Table\\Table;\n", "", 1)

open(p, "w", encoding="utf-8").write(s)
print("contactEmail fixed OK")
