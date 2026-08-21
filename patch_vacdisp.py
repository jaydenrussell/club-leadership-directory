# -*- coding: utf-8 -*-
import io, os
p = r"C:\Users\Jayden Russell\AppData\Local\hermes\attachments\club-leadership\com_clubleaddir\admin\helpers.php"
s = io.open(p, encoding="utf-8").read()

new_method = '''    /**
     * Resolve the global vacancy-enquiry target (from the component's global
     * Options) into a human-readable string for display in the admin record
     * form. The per-record "Vacancy Enquiry Email" field was removed; the
     * enquiry always follows these global settings.
     *
     * Priority: Joomla Contact (by id) -> default email -> hardcoded club inbox.
     *
     * @return string
     */
    public static function vacancyEnquiryDisplay()
    {
        try {
            $params    = \\Joomla\\CMS\\Component\\ComponentHelper::getParams('com_clubleaddir');
            $contactId = (int) $params->get('vacant_contact_id', 0);
            $defaultEm = trim((string) $params->get('vacancy_default_email', ''));
        } catch (\\Throwable $e) {
            $contactId = 0;
            $defaultEm = '';
        }

        if ($contactId > 0) {
            $db    = \\Joomla\\CMS\\Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(array('a.name', 'a.email_to'))
                ->from($db->quoteName('#__contact_details', 'a'))
                ->where($db->quoteName('a.id') . ' = ' . (int) $contactId);
            $db->setQuery($query);
            $row = $db->loadObject();
            if ($row) {
                $email = trim($row->email_to ?? '');
                return Text::sprintf('COM_CLUBLEADDIR_VACANCY_USES_CONTACT', $row->name, $email);
            }
        }

        if ($defaultEm !== '') {
            return Text::sprintf('COM_CLUBLEADDIR_VACANCY_USES_EMAIL', $defaultEm);
        }

        return Text::sprintf('COM_CLUBLEADDIR_VACANCY_USES_DEFAULT', self::vacancyEmail());
    }

'''

anchor = "    public static function contactHtml($person, $showContact, $contactHiddenText, $vacantContactId = 0"
assert anchor in s, "contactHtml anchor not found"
if "vacancyEnquiryDisplay" in s:
    print("already present")
else:
    s = s.replace(anchor, new_method + anchor, 1)
    io.open(p, "w", encoding="utf-8").write(s)
    print("helper method added")
