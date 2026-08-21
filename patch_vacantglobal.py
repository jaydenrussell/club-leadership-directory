# -*- coding: utf-8 -*-
import io, os

def edit(path, repls):
    s = io.open(path, encoding="utf-8").read()
    for old, new in repls:
        if old not in s:
            raise SystemExit("NOT FOUND in %s:\n%r" % (path, old[:120]))
        s = s.replace(old, new, 1)
    io.open(path, "w", encoding="utf-8").write(s)
    print("patched", path)

H = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\helpers.php"

# --- Rewrite contactHtml body so vacant uses ONLY global Vacant Enquiry Contact -> Vacancy Default Email ---
old = '''    public static function contactHtml($person, $showContact, $contactHiddenText, $recordContactId = 0, $vacantContactId = 0, $vacancyDefaultEmail = '')
    {
        if (empty($person->vacant)) {
            // Occupied position: show the real person's contact (record contact_id or stored email).
            $recordContactId = (int) ($person->contact_id ?? 0);
            if ($recordContactId > 0) {
                $contact = self::contactRow($recordContactId);
                if ($contact) {
                    $url   = Route::_('index.php?option=com_contact&view=contact&id=' . (int) $contact->id . '#display-form');
                    $label = Text::sprintf('COM_CLUBLEADDIR_CONTACT_PREFIX', htmlspecialchars($contact->name, ENT_QUOTES, 'UTF-8'));
                    return '<a class="clubleadership-card-contact" href="' . $url . '">' . $label . '</a>';
                }
            }
            $email = trim($person->email ?? '');
            if ($email !== '') {
                return '<a class="clubleadership-card-contact" href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CLUBLEADDIR_CONTACT') . '</a>';
            }
            if (!$showContact) {
                $text = trim($contactHiddenText);
                return $text !== '' ? '<span class="clubleadership-card-contact clubleadership-card-contact--hidden">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>' : '';
            }
            return '';
        }

        // Vacant position: use the global Vacant Enquiry Contact, then the global Vacancy Default Email.
        $effectiveContactId = (int) $vacantContactId;
        if ($effectiveContactId > 0) {
            $contact = self::contactRow($effectiveContactId);
            if ($contact) {
                $url   = Route::_('index.php?option=com_contact&view=contact&id=' . (int) $contact->id . '#display-form');
                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
                return '<a class="clubleadership-card-contact clubleaddership-card-contact--vacant" href="' . $url . '">' . $label . '</a>';
            }
        }

        $vacancyEmail = trim($person->vacancy_email ?? '');
        if ($vacancyEmail === '') {
            $vacancyEmail = trim($vacancyDefaultEmail);
        }
        if ($vacancyEmail !== '') {
            return '<a class="clubleadership-card-contact clubleaddership-card-contact--vacant" href="mailto:' . htmlspecialchars($vacancyEmail, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE') . '</a>';
        }

        return '';
    }'''

new = '''    /**
     * Renders the contact / vacancy-enquiry link for one person.
     *
     * Occupied position: shows that person's own contact (record contact_id or stored email).
     * Vacant position:   uses ONLY the global Vacant Enquiry Contact, failing that the global
     *                    Vacancy Default Email. A vacant position never falls back to a record contact.
     *
     * @param object  $person               The leadership record (needs ->vacant, ->contact_id, ->email).
     * @param bool    $showContact          Whether contact info is visible to the current user.
     * @param string  $contactHiddenText    Text shown when contact is hidden behind login.
     * @param int     $vacantContactId      GLOBAL Vacant Enquiry Contact id (module/menu param).
     * @param string  $vacancyDefaultEmail  GLOBAL Vacancy Default Email (module/menu param).
     * @return string
     */
    public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0, $vacancyDefaultEmail = '')
    {
        // Occupied position: show the real person's own contact details.
        if (empty($person->vacant)) {
            $recordContactId = (int) ($person->contact_id ?? 0);
            if ($recordContactId > 0) {
                $contact = self::contactRow($recordContactId);
                if ($contact) {
                    $url   = Route::_('index.php?option=com_contact&view=contact&id=' . (int) $contact->id . '#display-form');
                    $label = Text::sprintf('COM_CLUBLEADDIR_CONTACT_PREFIX', htmlspecialchars($contact->name, ENT_QUOTES, 'UTF-8'));
                    return '<a class="clubleadership-card-contact" href="' . $url . '">' . $label . '</a>';
                }
            }
            $email = trim($person->email ?? '');
            if ($email !== '') {
                return '<a class="clubleadership-card-contact" href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CLUBLEADDIR_CONTACT') . '</a>';
            }
            if (!$showContact) {
                $text = trim($contactHiddenText);
                return $text !== '' ? '<span class="clubleadership-card-contact clubleadership-card-contact--hidden">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>' : '';
            }
            return '';
        }

        // Vacant position: global Vacant Enquiry Contact first, then global Vacancy Default Email.
        $vacantContactId = (int) $vacantContactId;
        if ($vacantContactId > 0) {
            $contact = self::contactRow($vacantContactId);
            if ($contact) {
                $url   = Route::_('index.php?option=com_contact&view=contact&id=' . (int) $contact->id . '#display-form');
                $label = Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE');
                return '<a class="clubleadership-card-contact clubleaddership-card-contact--vacant" href="' . $url . '">' . $label . '</a>';
            }
        }

        $vacancyEmail = trim($vacancyDefaultEmail);
        if ($vacancyEmail !== '') {
            return '<a class="clubleadership-card-contact clubleaddership-card-contact--vacant" href="mailto:' . htmlspecialchars($vacancyEmail, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CLUBLEADDIR_VACANCY_INQUIRE') . '</a>';
        }

        return '';
    }'''

edit(H, [(old, new)])

# --- Fix component view call: vacant_contact_id is the GLOBAL vacant contact, pass as $vacantContactId ---
V = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\views\leaderships\tmpl\default.php"
old_v = "<?php echo ClubleaddirHelper::contactHtml($person, true, '', (int) ($this->params->get('vacant_contact_id', 0)), (string) ($this->params->get('vacancy_default_email', ''))); ?>"
new_v = "<?php echo ClubleaddirHelper::contactHtml($person, true, '', (int) ($this->params->get('vacant_contact_id', 0)), (string) ($this->params->get('vacancy_default_email', ''))); ?>"
# (signature now matches: $vacantContactId, $vacancyDefaultEmail) -> no change needed, but keep explicit:
edit(V, [(old_v, new_v)])

print("DONE")
