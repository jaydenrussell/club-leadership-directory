# -*- coding: utf-8 -*-
import io, os

def patch(path, repls):
    s = io.open(path, encoding="utf-8").read()
    changed = False
    for old, new in repls:
        if old in s:
            s = s.replace(old, new)
            changed = True
        elif new in s:
            pass  # already applied
        else:
            raise AssertionError("NOT FOUND in %s:\n%s" % (path, old[:160]))
    io.open(path, "w", encoding="utf-8").write(s)
    print("patched" if changed else "already-applied", path)

ROOT = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership"

# ---------- 1) Helper ----------
h = os.path.join(ROOT, "com_clubleaddir/admin/helpers.php")
patch(h, [
    ("    public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0)\n",
     "    public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = 'info@simcoecurlingclub.ca')\n"),
    ("        $vacancyEmail  = trim($person->vacancy_email ?? '');\n"
     "        if ($vacancyEmail === '') {\n"
     "            $vacancyEmail = self::vacancyEmail();\n"
     "        }\n",
     "        $vacancyEmail  = trim($person->vacancy_email ?? '');\n"
     "        if ($vacancyEmail === '') {\n"
     "            $vacancyEmail = trim($vacancyDefaultEmail);\n"
     "        }\n"),
])

# ---------- 2) Module tmpl ----------
m = os.path.join(ROOT, "mod_clubleaddir/tmpl/default.php")
patch(m, [
    ("function clubleaddirRenderCard($person, $showPhoto, $showContact, $contactHiddenText, $showTerm, $circular = 1, $photoSize = 120, $vacantContactId = 0)\n",
     "function clubleaddirRenderCard($person, $showPhoto, $showContact, $contactHiddenText, $showTerm, $circular = 1, $photoSize = 120, $vacantContactId = 0, $vacancyDefaultEmail = 'info@simcoecurlingclub.ca')\n"),
    # NOTE: this line is identical at the two call sites; global replace covers both
    ("    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId);\n",
     "    $contactHtml = clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId, $vacancyDefaultEmail);\n"),
    ("function clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId = 0)\n",
     "function clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = 'info@simcoecurlingclub.ca')\n"),
    ("function clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0)\n",
     "function clubleaddirRenderContactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = 'info@simcoecurlingclub.ca')\n"),
    ("    return ClubleaddirHelper::contactHtml($person, $showContact, $contactHiddenText, $vacantContactId);\n",
     "    return ClubleaddirHelper::contactHtml($person, $showContact, $contactHiddenText, $vacantContactId, $vacancyDefaultEmail);\n"),
    ("clubleaddirRenderCard($person, $showPhotosOfficers, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId); ?>",
     "clubleaddirRenderCard($person, $showPhotosOfficers, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId, $vacancyDefaultEmail); ?>"),
    ("clubleaddirRenderCard($person, $showPhotosDirectors, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId); ?>",
     "clubleaddirRenderCard($person, $showPhotosDirectors, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId, $vacancyDefaultEmail); ?>"),
    ("clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId); ?>",
     "clubleaddirRenderLeagueCard($person, $showContact, $contactHiddenText, $vacantContactId, $vacancyDefaultEmail); ?>"),
    ("clubleaddirRenderCard($person, $showPhotosStaff, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId); ?>",
     "clubleaddirRenderCard($person, $showPhotosStaff, $showContact, $contactHiddenText, $showTerm, $circularAvatars, $photoSize, $vacantContactId, $vacancyDefaultEmail); ?>"),
])

# ---------- 3) Component view ----------
c = os.path.join(ROOT, "com_clubleaddir/views/leaderships/tmpl/default.php")
patch(c, [
    ("<?php echo ClubleaddirHelper::contactHtml($person, true, '', (int) ($this->params->get('vacant_contact_id', 0))); ?>",
     "<?php echo ClubleaddirHelper::contactHtml($person, true, '', (int) ($this->params->get('vacant_contact_id', 0)), (string) ($this->params->get('vacancy_default_email', 'info@simcoecurlingclub.ca'))); ?>"),
])

print("ALL PATCHES APPLIED")
