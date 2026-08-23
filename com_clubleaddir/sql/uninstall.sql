-- uninstall SQL: clean up component data
DELETE FROM #__clubleaddir_leaders WHERE 1;
DELETE FROM #__modules WHERE module = 'mod_clubleaddir';
DELETE FROM #__menu WHERE link LIKE '%com_clubleaddir%';
