# -*- coding: utf-8 -*-
p = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\helpers.php"
s = open(p, encoding="utf-8").read()

method = (
    "    /**\n"
    "     * Render an engaging \"we have vacancies — step up!\" recruitment banner.\n"
    "     * Shown at the top of the directory when at least one position is vacant.\n"
    "     * The CTA opens the Joomla Contact's EMAIL FORM directly (layout=edit),\n"
    "     * not the contact's profile page; falls back to a mailto: when no\n"
    "     * contact is configured.\n"
    "     *\n"
    "     * @param int    $contactId     Resolved Joomla Contact id for vacant enquiries\n"
    "     * @param string $defaultEmail  Fallback email when no contact id is set\n"
    "     * @return string\n"
    "     */\n"
    "    public static function vacancyBannerHtml($contactId, $defaultEmail = 'info@simcoecurlingclub.ca')\n"
    "    {\n"
    "        $contactId = (int) $contactId;\n"
    "        if ($contactId > 0) {\n"
    "            $url = Route::_('index.php?option=com_contact&view=contact&id=' . $contactId . '&layout=edit');\n"
    "            $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');\n"
    "        } else {\n"
    "            $url = 'mailto:' . htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8');\n"
    "        }\n"
    "\n"
    "        return '<div class=\"clubleaddir-vacancy-banner\" role=\"status\">'\n"
    "            . '<div class=\"clubleaddir-vacancy-banner-icon\" aria-hidden=\"true\">&#128101;</div>'\n"
    "            . '<div class=\"clubleaddir-vacancy-banner-body\">'\n"
    "                . '<h3 class=\"clubleaddir-vacancy-banner-title\">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_TITLE'), ENT_QUOTES, 'UTF-8') . '</h3>'\n"
    "                . '<p class=\"clubleaddir-vacancy-banner-text\">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_BODY'), ENT_QUOTES, 'UTF-8') . '</p>'\n"
    "            . '</div>'\n"
    "            . '<a class=\"clubleaddir-vacancy-banner-cta\" href=\"' . $url . '\">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_CTA'), ENT_QUOTES, 'UTF-8') . '</a>'\n"
    "            . '</div>';\n"
    "    }\n"
    "}\n"
)

assert s.rstrip().endswith("}"), "class does not end with }"
s = s.rstrip()
if s.endswith("}"):
    s = s[:-1].rstrip() + "\n" + method
else:
    s = s + "\n" + method

open(p, "w", encoding="utf-8").write(s)
print("vacancyBannerHtml added OK")
