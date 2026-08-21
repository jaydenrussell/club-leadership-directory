# -*- coding: utf-8 -*-
import io, os

def edit(path, repls):
    s = io.open(path, encoding="utf-8").read()
    for old, new in repls:
        if old not in s:
            raise SystemExit("NOT FOUND in %s:\n%s" % (path, old))
        s = s.replace(old, new, 1) if old.endswith(("'", '"', " ", "\n")) else s.replace(old, new)
    io.open(path, "w", encoding="utf-8").write(s)
    print("patched:", path)

H = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\helpers.php"
V = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\views\leaderships\tmpl\default.php"
M = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\mod_clubleaddir\mod_clubleaddir.php"
INI = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\language\en-GB\en-GB.com_clubleaddir.ini"

# --- helpers.php ---
edit(H, [
    # contactHtml: default email param -> empty (no hardcoded info@)
    ("public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = 'info@simcoecurlingclub.ca')",
     "public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = '')"),
    # contactHtml: no contact + no email -> no link
    ("""            } else {
                $url   = 'mailto:' . $vacancyEmail;
                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
            }""",
     """            } else {
                if ($vacancyEmail === '') {
                    return ''; // No Joomla Contact and no Vacancy Default Email configured.
                }
                $url   = 'mailto:' . $vacancyEmail;
                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
            }"""),
    # vacancyEnquiryDisplay: last-resort info@ -> not configured
    ("        return Text::sprintf('COM_CLUBLEADDIR_VACANCY_USES_DEFAULT', self::vacancyEmail());",
     "        return Text::_('COM_CLUBLEADDIR_VACANCY_USES_NONE');"),
    # vacancyBannerHtml: default email param -> empty
    ("public static function vacancyBannerHtml($contactId, $defaultEmail = 'info@simcoecurlingclub.ca')",
     "public static function vacancyBannerHtml($contactId, $defaultEmail = '')"),
    # vacancyBannerHtml: no contact + no email -> no CTA; banner still shows without CTA
    ("""        } else {
            $url = 'mailto:' . htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8');
        }

        return '<div class="clubleaddir-vacancy-banner" role="status">'
            . '<div class="clubleaddir-vacancy-banner-icon" aria-hidden="true">&#128101;</div>'
            . '<div class="clubleaddir-vacancy-banner-body">'
                . '<h3 class="clubleaddir-vacancy-banner-title">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_TITLE'), ENT_QUOTES, 'UTF-8') . '</h3>'
                . '<p class="clubleaddir-vacancy-banner-text">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_BODY'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
            . '<a class="clubleaddir-vacancy-banner-cta" href="' . $url . '">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_CTA'), ENT_QUOTES, 'UTF-8') . '</a>'
            . '</div>';""",
     """        } else {
            if ($defaultEmail === '') {
                // No Joomla Contact and no Vacancy Default Email configured -> no CTA link.
                $url = '';
            } else {
                $url = 'mailto:' . htmlspecialchars($defaultEmail, ENT_QUOTES, 'UTF-8');
            }
        }

        $cta = '';
        if ($url !== '') {
            $cta = '<a class="clubleaddir-vacancy-banner-cta" href="' . $url . '">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_CTA'), ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return '<div class="clubleaddir-vacancy-banner" role="status">'
            . '<div class="clubleaddir-vacancy-banner-icon" aria-hidden="true">&#128101;</div>'
            . '<div class="clubleaddir-vacancy-banner-body">'
                . '<h3 class="clubleaddir-vacancy-banner-title">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_TITLE'), ENT_QUOTES, 'UTF-8') . '</h3>'
                . '<p class="clubleaddir-vacancy-banner-text">' . htmlspecialchars(Text::_('COM_CLUBLEADDIR_VACANCIES_BODY'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
            . $cta
            . '</div>';"""),
])

# --- component view: drop hardcoded info@ defaults ---
edit(V, [
    ("(string) ($this->params->get('vacancy_default_email', 'info@simcoecurlingclub.ca'))",
     "(string) ($this->params->get('vacancy_default_email', ''))"),
])

# --- module php: drop hardcoded info@ default ---
edit(M, [
    ("$vacancyDefaultEmail = (string) $paramsData->get('vacancy_default_email', 'info@simcoecurlingclub.ca');",
     "$vacancyDefaultEmail = (string) $paramsData->get('vacancy_default_email', '');"),
])

# --- language: add not-configured string ---
edit(INI, [
    ("COM_CLUBLEADDIR_VACANCY_USES_DEFAULT=\"Club default: %s\"\n",
     "COM_CLUBLEADDIR_VACANCY_USES_DEFAULT=\"Club default: %s\"\nCOM_CLUBLEADDIR_VACANCY_USES_NONE=\"Not configured — set a Joomla Contact or default email in Component Options (or the module Contact settings).\"\n"),
])
print("ALL DONE")
