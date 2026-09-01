/**
 * Club Leadership Directory — administrator edit form behaviour.
 * Shipped by com_clubleaddir to /media/com_clubleaddir/js/admin-edit.js.
 */

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
	var staffWrap = document.getElementById('staff-fields');
	if (staffWrap) {
		staffWrap.style.display = (type === 'staff') ? 'block' : 'none';
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
	var photo = document.getElementById('photo');
	if (photo) { photo.disabled = isVacant; }
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

function clblePreviewPhoto(input) {
	var box = document.getElementById('photo_preview');
	if (!box) { return; }
	if (!input.files || !input.files[0]) {
		return;
	}
	var file = input.files[0];
	var name = document.createElement('p');
	name.className = 'help-block';
	name.style.cssText = 'word-break:break-all;font-size:11px;margin-top:4px;';
	name.textContent = file.name + ' (' + (file.size ? Math.round(file.size / 1024) + ' KB' : '') + ')';

	if (box.querySelector('img')) { box.querySelector('img').remove(); }
	if (box.querySelector('.clble-photo-placeholder')) { box.querySelector('.clble-photo-placeholder').remove(); }
	var old = box.querySelector('p.help-block');
	if (old) { old.remove(); }

	if (window.FileReader && file.type.indexOf('image/') === 0) {
		var reader = new FileReader();
		reader.onload = function (e) {
			var img = document.createElement('img');
			img.src = e.target.result;
			img.alt = '';
			img.className = 'thumbnail';
			img.style.cssText = 'max-width:140px;max-height:140px;display:inline-block;vertical-align:middle;';
			box.appendChild(img);
			box.appendChild(name);
		};
		reader.readAsDataURL(file);
	} else {
		box.appendChild(name);
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

(function () {
	var typeEl = document.getElementById('type');
	if (typeEl) {
		toggleTypeFields(typeEl.value);
	}
	var vacantEl = document.getElementById('vacant');
	if (vacantEl) {
		toggleVacantFields(vacantEl.checked);
	}
})();
