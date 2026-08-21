# -*- coding: utf-8 -*-
p = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\mod_clubleaddir\tmpl\default.php"
s = open(p, encoding="utf-8").read()

# 1) League card signature + body (handle vacant, thread vacantContactId)
old_league = (
    "function clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText)\n"
    "{\n"
    "    $roleHtml   = '<div class=\"clubleadership-card-role\">' . Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_REP_TITLE') . '</div>';\n"
    "    $leagueHtml = '';\n"
    "    if (!empty($person->league_name)) {\n"
    "        $leagueHtml = '<div class=\"clubleadership-card-league\">' . htmlspecialchars($person->league_name, ENT_QUOTES, 'UTF-8') . '</div>';\n"
    "    }\n"
    "    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText);\n"
    "\n"
    "    return '<article class=\"clubleadership-card clubleaddir-card--director\">'\n"
    "        . '<div class=\"clubleadership-card-photo\"></div>'\n"
    "        . '<div class=\"clubleadership-card-content\">'\n"
    "        . '<h4 class=\"clubleadership-card-name\">' . htmlspecialchars($person->name, ENT_QUOTES, 'UTF-8') . '</h4>'\n"
    "        . $roleHtml\n"
    "        . $leagueHtml\n"
    "        . $contactHtml\n"
    "        . '</div>'\n"
    "        . '</article>';\n"
    "}\n"
    "\n"
    "function clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText)\n"
    "{\n"
    "    // Delegate to the shared helper so the module and the component view render\n"
    "    // contact (Joomla Contact link, vacancy Apply/Inquire, or email/phone) identically.\n"
    "    if (!class_exists('ClubleaddirHelper', false)) {\n"
    "        require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';\n"
    "    }\n"
    "    return ClubleaddirHelper::contactHtml($person, $showContact, $contactHiddenText);\n"
    "}\n"
)
new_league = (
    "function clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId = 0)\n"
    "{\n"
    "    $isVacant  = !empty($person->vacant);\n"
    "    $nameEmpty = $isVacant && empty(trim($person->name ?? ''));\n"
    "    $displayName = $nameEmpty ? ($person->role ?? Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_REP_TITLE')) : $person->name;\n"
    "    $roleHtml   = '<div class=\"clubleadership-card-role\">' . Text::_('MOD_CLUBLEADDIRECTION_LEAGUE_REP_TITLE') . '</div>';\n"
    "    $leagueHtml = '';\n"
    "    if (!empty($person->league_name)) {\n"
    "        $leagueHtml = '<div class=\"clubleadership-card-league\">' . htmlspecialchars($person->league_name, ENT_QUOTES, 'UTF-8') . '</div>';\n"
    "    }\n"
    "    $metaHtml = $roleHtml . $leagueHtml;\n"
    "    if ($isVacant) {\n"
    "        $metaHtml .= '<span class=\"clubleadership-card-vacant\">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANT'), ENT_QUOTES, 'UTF-8') . '</span>';\n"
    "    }\n"
    "    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId);\n"
    "\n"
    "    return '<article class=\"clubleadership-card clubleaddir-card--director' . ($isVacant ? ' clubleaddir-card--vacant' : '') . '\">'\n"
    "        . '<div class=\"clubleadership-card-photo\"></div>'\n"
    "        . '<div class=\"clubleadership-card-content\">'\n"
    "        . '<h4 class=\"clubleadership-card-name\">' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</h4>'\n"
    "        . $metaHtml\n"
    "        . $contactHtml\n"
    "        . '</div>'\n"
    "        . '</article>';\n"
    "}\n"
    "\n"
    "function clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0)\n"
    "{\n"
    "    // Delegate to the shared helper so the module and the component view render\n"
    "    // contact (Joomla Contact link, vacancy Apply/Inquire, or email/phone) identically.\n"
    "    if (!class_exists('ClubleaddirHelper', false)) {\n"
    "        require_once JPATH_ADMINISTRATOR . '/components/com_clubleaddir/helpers.php';\n"
    "    }\n"
    "    return ClubleaddirHelper::contactHtml($person, $showContact, $contactHiddenText, $vacantContactId);\n"
    "}\n"
)
assert old_league in s, "league block not found"
s = s.replace(old_league, new_league, 1)

# 2) League card call site: pass $vacantContactId
s = s.replace(
    "clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText);",
    "clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId);",
    1
)

# 3) Officer/Director/Staff card call sites: pass $vacantContactId
s = s.replace(
    "clubleaddirRenderCard($person, $showPhotosOfficers, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize);",
    "clubleaddirRenderCard($person, $showPhotosOfficers, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId);",
    1
)
s = s.replace(
    "clubleaddirRenderCard($person, $showPhotosDirectors, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize);",
    "clubleaddirRenderCard($person, $showPhotosDirectors, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId);",
    1
)
s = s.replace(
    "clubleaddirRenderCard($person, $showPhotosStaff, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize);",
    "clubleaddirRenderCard($person, $showPhotosStaff, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId);",
    1
)

open(p, "w", encoding="utf-8").write(s)
print("module tmpl updated OK")
