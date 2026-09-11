/**
 * Club Leadership Directory — administrator edit form behaviour.
 * Shipped by com_clubleaddir to /media/com_clubleaddir/js/admin-edit.js.
 */

(function () {
	'use strict';

	function onTypeChange() {
		var el = document.getElementById('type');
		if (el) {
			toggleTypeFields(el.value);
		}
	}

	function onRoleSelectChange() {
		var sel = document.getElementById('role_select');
		var hidden = document.getElementById('role');
		if (sel && hidden) {
			hidden.value = sel.value;
		}
	}

	function onRoleTextInput() {
		var text = document.getElementById('role_text');
		var hidden = document.getElementById('role');
		if (text && hidden) {
			hidden.value = text.value;
		}
	}

	function onVacantChange() {
		var el = document.getElementById('vacant');
		if (el) {
			toggleVacantFields(el.checked);
		}
	}

	function onBioToggle() {
		var el = document.getElementById('bio_enabled');
		if (el) {
			toggleBioFields(el.checked);
		}
	}

	function onPhoneInput() {
		clbleStripPhone(this);
	}


function init() {
    var typeEl = document.getElementById('type');
    if (typeEl) {
        typeEl.addEventListener('change', onTypeChange);
        toggleTypeFields(typeEl.value);
    }

    var roleSelect = document.getElementById('role_select');
    if (roleSelect) {
        roleSelect.addEventListener('change', onRoleSelectChange);
    }

    var roleText = document.getElementById('role_text');
    if (roleText) {
        roleText.addEventListener('input', onRoleTextInput);
    }

    var vacantEl = document.getElementById('vacant');
    if (vacantEl) {
        vacantEl.addEventListener('change', onVacantChange);
        toggleVacantFields(vacantEl.checked);
    }

    var bioEl = document.getElementById('bio_enabled');
    if (bioEl) {
        bioEl.addEventListener('change', onBioToggle);
        toggleBioFields(bioEl.checked);
    }

    var phoneEl = document.getElementById('phone');
    if (phoneEl) {
        phoneEl.addEventListener('input', onPhoneInput);
    }
}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

function toggleTypeFields(type) {
	var leagueWrap = document.getElementById('league-fields');
	if (type === 'director_league') {
		leagueWrap.style.display = 'block';
	} else {
		leagueWrap.style.display = 'none';
		var lsel = document.getElementById('league_name');
		if (lsel) { lsel.value = ''; }
	}
	var isOfficer = (type === 'officer');
	var isLeague = (type === 'director_league');
	var roleSelect = document.getElementById('role_select');
	var roleText   = document.getElementById('role_text');
	var roleHidden = document.getElementById('role');
	var roleGroup  = document.getElementById('role-control-group');
	if (isOfficer) {
		roleSelect.style.display = 'block';
		roleText.style.display = 'none';
		roleText.value = '';
		roleHidden.value = roleSelect.value;
		setRoleDisabled(false);
	} else if (isLeague) {
		roleSelect.style.display = 'none';
		roleText.style.display = 'none';
		roleHidden.value = '';
		roleText.value = '';
		roleSelect.value = '';
		setRoleDisabled(true);
	} else {
		roleSelect.style.display = 'none';
		roleText.style.display = 'block';
		roleHidden.value = '';
		roleText.value = '';
		roleSelect.value = '';
		setRoleDisabled(false);
	}
	setRoleRequired();
}

function setRoleDisabled(disabled) {
	var group = document.getElementById('role-control-group');
	if (group) {
		group.classList.toggle('clble-disabled', disabled);
	}
	var inputs = ['role_text', 'role_select'];
	inputs.forEach(function (id) {
		var el = document.getElementById(id);
		if (el) { el.disabled = disabled; }
	});
}

function setRoleRequired() {
	var isVacant = document.getElementById('vacant')
		? document.getElementById('vacant').checked
		: false;
	['role_text', 'role_select'].forEach(function (id) {
		var el = document.getElementById(id);
		if (!el) { return; }
		var visible = (el.style.display !== 'none') && (getComputedStyle(el).display !== 'none');
		if (isVacant && visible) {
			el.setAttribute('required', 'required');
		} else {
			el.removeAttribute('required');
		}
	});
}

function toggleVacantFields(isVacant) {
	var vacantSettings = document.getElementById('vacant-settings');
	if (vacantSettings) {
		vacantSettings.style.display = isVacant ? 'block' : 'none';
	}
	var fieldset = document.getElementById('contact-info-fieldset');
	if (fieldset) {
		fieldset.classList.toggle('clble-disabled', isVacant);
		['contact_id', 'email', 'phone'].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) { el.disabled = isVacant; }
		});
	}
	var photo = document.getElementById('jform_photo');
	if (photo) {
		photo.disabled = isVacant;
		var wrap = photo.closest('.controls') || photo.parentElement;
		var buttons = wrap.querySelectorAll('button');
		for (var b = 0; b < buttons.length; b++) { buttons[b].disabled = isVacant; }
	}
	setRoleRequired();
	var nameEl = document.getElementById('name');
	if (nameEl) {
		if (isVacant) { nameEl.removeAttribute('required'); }
		else { nameEl.setAttribute('required', 'required'); }
	}
	var nameLabel = document.querySelector('label[for="name"]');
	if (nameLabel) {
		var star = nameLabel.querySelector('.star');
		if (isVacant && star) { star.remove(); }
		else if (!isVacant && !star) {
			var s = document.createElement('span');
			s.className = 'star'; s.textContent = '*';
			nameLabel.appendChild(s);
		}
	}
	var roleLabel = document.querySelector('#role-control-group .control-label label');
	if (roleLabel) {
		var star = roleLabel.querySelector('.star');
		if (isVacant && !star) {
			var s = document.createElement('span');
			s.className = 'star'; s.textContent = '*';
			roleLabel.appendChild(s);
		} else if (!isVacant && star) {
			star.remove();
		}
	}
}

function toggleLeagueFields(type) { toggleTypeFields(type); }

function toggleBioFields(enabled) {
	var wrap = document.getElementById('bio-wrap');
	if (wrap) {
		wrap.style.display = enabled ? 'block' : 'none';
	}
	var text = document.getElementById('bio');
	if (text) {
		text.disabled = !enabled;
	}
}



function clbleStripPhone(input) {
	input.value = input.value.replace(/[^0-9+\-\s()]/g, '');
}

function clbleValidateEmail(input) {
	var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	return !input.value || re.test(input.value);
}

Joomla.submitbutton = function (task) {
	if (task === 'leadership.cancel') {
		Joomla.submitform(task, document.getElementById('adminForm'));
		return;
	}
	var form = document.getElementById('adminForm');
	var ok = true;
	var prev = form.querySelectorAll('.clble-invalid');
	for (var i = 0; i < prev.length; i++) { prev[i].classList.remove('clble-invalid'); }
	var req = form.querySelectorAll('[required]');
	for (var j = 0; j < req.length; j++) {
		var el = req[j];
		if (el.id === 'league_name' && document.getElementById('league-fields').style.display === 'none') { continue; }
		if (!el.value || !el.value.trim()) { el.classList.add('clble-invalid'); ok = false; }
	}
	var emailEl = document.getElementById('email');
	if (emailEl && emailEl.value && !clbleValidateEmail(emailEl)) {
		emailEl.classList.add('clble-invalid'); ok = false;
	}
	var bioEnable = document.getElementById('bio_enabled');
	var bioText = document.getElementById('bio');
	if (bioText && bioEnable && !bioEnable.checked) {
		bioText.value = '';
	}
	if (!ok) {
		alert(Joomla.JText._('COM_CLUBLEADDIR_ERROR_REQUIRED_FIELDS'));
		return;
	}
	Joomla.submitform(task, form);
};

function jClubleaddirSelectContact(id, name) {
	var field = document.getElementById('contact_id');
	if (field) { field.value = id; }
	var disp = document.getElementById('contact_name_display');
	if (disp) { disp.textContent = name; }
	if (window.parent.SqueezeBox) { window.parent.SqueezeBox.close(); }
	else if (window.parent.jModalClose) { window.parent.jModalClose(); }
	return false;
}
