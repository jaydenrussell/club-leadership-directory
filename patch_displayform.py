# -*- coding: utf-8 -*-
p = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\helpers.php"
s = open(p, encoding="utf-8").read()

# --- 1) Banner CTA -> contact email form via #display-form (idempotent) ---
old_banner = "            $url = Route::_('index.php?option=com_contact&view=contact&id=' . $contactId . '&layout=edit');"
if old_banner in s:
    new_banner = (
        "            // Blend into the Joomla Contact component: open the email form directly\n"
        "            // (contact profile above + form anchored via #display-form) - nicer than mailto.\n"
        "            $url = Route::_('index.php?option=com_contact&view=contact&id=' . $contactId . '#display-form');"
    )
    s = s.replace(old_banner, new_banner)
    print("banner updated")
else:
    print("banner already updated or not found (skip)")

# --- 2) Per-card vacant 'Inquire' -> contact form instead of raw mailto ---
old_card = (
    "        if ($vacant === 1) {\n"
    "            $vacantContact = ($contactId > 0) ? $contactId : (int) $vacantContactId;\n"
    "            $contactEmail  = self::contactEmail($vacantContact);\n"
    "            if ($contactEmail !== '') {\n"
    "                $url   = 'mailto:' . $contactEmail;\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');\n"
    "            } else {\n"
    "                $url   = 'mailto:' . $vacancyEmail;\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');\n"
    "            }"
)
assert old_card in s, "card vacant branch not found (already changed?)"
new_card = (
    "        if ($vacant === 1) {\n"
    "            $vacantContact = ($contactId > 0) ? $contactId : (int) $vacantContactId;\n"
    "            if ($vacantContact > 0) {\n"
    "                // Blend into the Joomla Contact component: open the email form directly.\n"
    "                $url   = Route::_('index.php?option=com_contact&view=contact&id=' . $vacantContact . '#display-form');\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');\n"
    "            } else {\n"
    "                $url   = 'mailto:' . $vacancyEmail;\n"
    "                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');\n"
    "            }"
)
s = s.replace(old_card, new_card)
print("card vacant branch updated")

open(p, "w", encoding="utf-8").write(s)
print("helper saved")
