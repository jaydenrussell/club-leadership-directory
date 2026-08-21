# -*- coding: utf-8 -*-
import io, os
p = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\views\leadership\tmpl\default.php"
s = io.open(p, encoding="utf-8").read()

old = (
    "function toggleVacantFields(isVacant) {\n"
    "    // Vacancy enquiry email stays active \u2014 it IS what gets used.\n"
    "    var ve = document.getElementById('vacancy-email-group');\n"
    "    if (ve) { ve.style.display = isVacant ? 'block' : 'none'; }\n"
    "\n"
    "    // Grey + disable the personal contact fields (email / phone / Joomla contact).\n"
)
new = (
    "function toggleVacantFields(isVacant) {\n"
    "    // The vacancy enquiry target is shown as a read-only label next to the\n"
    "    // Vacant checkbox (it follows the global settings) \u2014 nothing to toggle here.\n"
    "\n"
    "    // Grey + disable the personal contact fields (email / phone / Joomla contact).\n"
)
assert old in s, "JS block not found"
s = s.replace(old, new, 1)
io.open(p, "w", encoding="utf-8").write(s)
print("JS updated")
