DELETE FROM `#__update_sites` WHERE `name` = 'Club Leadership Directory Update' AND `location` LIKE '%raw.githubusercontent.com%';

UPDATE `#__update_sites`
SET `location` = 'https://jaydenrussell.github.io/club-leadership-directory/update.xml',
    `enabled` = 1
WHERE `name` = 'Club Leadership Directory Update';

INSERT INTO `#__update_sites` (`name`, `type`, `location`, `enabled`, `last_check_timestamp`, `extra_query`)
SELECT 'Club Leadership Directory Update', 'extension',
       'https://jaydenrussell.github.io/club-leadership-directory/update.xml', 1, 0, ''
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `#__update_sites` WHERE `name` = 'Club Leadership Directory Update'
);
