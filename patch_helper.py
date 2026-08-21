# -*- coding: utf-8 -*-
p = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\helpers.php"
s = open(p, encoding="utf-8").read()

# 1) Add Table use statement
s = s.replace(
    "use Joomla\\CMS\\Router\\Route;\n",
    "use Joomla\\CMS\\Router\\Route;\nuse Joomla\\CMS\\Table\\Table;\n",
    1
)

# 2) contactHtml signature gains $vacantContactId
s = s.replace(
    "    public static function contactHtml($person, $showContact, $contactHiddenText)\n    {\n        $email         = $person->email ?? '';\n",
    "    public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0)\n    {\n        $email         = $person->email ?? '';\n",
    1
)

# 3) Replace the vacant branch
old_vacant = (
    "        // 1. Vacant position \u2014 invite volunteers to apply / enquire.\n"
    "        if ($vacant === 1) {\n"
    "            $role   = trim($person->role ?? '');\n"
    "            $subject = ($role !== '' ? $role . ' Vacancy' : 'Leadership Vacancy')\n"
    "                . ' \u2014 Simcoe Curling Club';\n"
    "            if ($contactId > 0) {\n"
    "                $url = Route::_('index.php?option=com_contact&view=contact&id=' . $contactId);\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_APPLY');\n"
    "            } else {\n"
    "                $url = 'mailto:' . $vacancyEmail . '?subject=' . rawurlencode($subject);\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');\n"
    "            }\n"
    "            return '<div class=\"clubleadership-card-contact\">'\n"
    "                . '<a href=\"' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '\" class=\"clubleadership-contact-link clubleaddir-vacancy-link\">'\n"
    "                . '<span class=\"icon-mail\" aria-hidden=\"true\"></span>'\n"
    "                . '<span class=\"clubleadership-contact-text\">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>'\n"
    "                . '</div>';\n"
    "        }\n"
)
new_vacant = (
    "        // 1. Vacant position \u2014 open the linked Joomla Contact's email\n"
    "        //    directly (mailto:, no contact page, no prefilled subject).\n"
    "        //    Falls back to a plain vacancy email when no contact is set.\n"
    "        if ($vacant === 1) {\n"
    "            $vacantContact = ($contactId > 0) ? $contactId : (int) $vacantContactId;\n"
    "            $contactEmail  = self::contactEmail($vacantContact);\n"
    "            if ($contactEmail !== '') {\n"
    "                $url   = 'mailto:' . $contactEmail;\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');\n"
    "            } else {\n"
    "                $url   = 'mailto:' . $vacancyEmail;\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');\n"
    "            }\n"
    "            return '<div class=\"clubleadership-card-contact\">'\n"
    "                . '<a href=\"' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '\" class=\"clubleadership-contact-link clubleaddir-vacancy-link\">'\n"
    "                . '<span class=\"icon-mail\" aria-hidden=\"true\"></span>'\n"
    "                . '<span class=\"clubleadership-contact-text\">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>'\n"
    "                . '</div>';\n"
    "        }\n"
)
assert old_vacant in s, "vacant branch not found"
s = s.replace(old_vacant, new_vacant, 1)

# 4) Add contactEmail() before the closing brace of the class
old_tail = (
    "    public static function vacantLogo()\n"
    "    {\n"
    "        return 'https://simcoecurlingclub.ca/images/Logo/simcoe_curling_club_logo.svg';\n"
    "    }\n"
    "}\n"
)
new_tail = (
    "    public static function vacantLogo()\n"
    "    {\n"
    "        return 'https://simcoecurlingclub.ca/images/Logo/simcoe_curling_club_logo.svg';\n"
    "    }\n"
    "\n"
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
assert old_tail in s, "tail not found"
s = s.replace(old_tail, new_tail, 1)

open(p, "w", encoding="utf-8").write(s)
print("helper updated OK")
