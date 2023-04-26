/*!
	Toggle Switch 1.0.1 - 2014-06-19
	jQuery Toggle Button
	(c) 2014, http://tinytools.codesells.com
	license: http://www.opensource.org/licenses/mit-license.php
*/

; (function ($, document, window) {
	var toggleSwitch = 'toggleSwitch';
	var toggleSwitchGeneralSettings;

	if ($.toggleSwitch) {
		return;
	}

	publicMethod = $.fn[toggleSwitch] = $[toggleSwitch] = function (options) {
		var settings = options;

		return this.each(function (i, obj) {
			initializeToggleSwitch(obj, settings);
		});
	};

	function setSettings(options) {
		var settings = $.extend({
			onLabel: "ON",
			offLabel: "OFF",
			width: "100px",
			height: "20px",

			//Events:
			onToggle: false
		}, options);

		return settings;
	}

	function getSettings(internalElement) {
		return internalElement.closest('.ToggleSwitch').data('settings');
	}

	function initializeToggleSwitch(obj, settings) {
		if ($(obj).is(':checkbox')) {
			var setting = setSettings({});
			setting = $.extend(setting, toggleSwitchGeneralSettings);
			settings = $.extend(setting, settings);

			var content = '<div class="TinyTools ToggleSwitch">';
			content += '<div class="NubWrapper' + ($(obj).is(':checked') ? ' Checked' : '') + ($(obj).is(':disabled') ? ' Disabled' : '') + '">';
			content += '<div class="OffSide"><span>' + settings.offLabel + '</span></div>';
			content += '<div class="OnSide"><span>' + settings.onLabel + '</span></div>';
			content += '<div class="Nub"></div>';
			content += '</div></div>';

			var $insertedObj = $(content);

			$(obj).css('display', 'none');

			if ($insertedObj.width() <= 0)
				$insertedObj.css('width', settings.width);
			if ($insertedObj.height() <= 0)
				$insertedObj.css('height', settings.height);

			$(obj).before($insertedObj);
			$insertedObj.append($(obj));
			$insertedObj.data("settings", settings);

			$(obj).change(function () {
				changeChecked($(this).closest(".ToggleSwitch"), $(this).is(':checked'), false);
				changeDisabled($(this).closest(".ToggleSwitch"), $(this).is(':disabled'), false);
			});

			$insertedObj.click(function () {
				if (!$(this).children('input[type="checkbox"]').is(':disabled'))
					changeChecked($(this), $(this).children('input[type="checkbox"]').is(':checked') ? false : true);
			});
		}
	}

	function changeChecked(caller, checked, changeCheckbox) {
		if (checked)
			$(caller).children('.NubWrapper').addClass('Checked');
		else
			$(caller).children('.NubWrapper').removeClass('Checked');

		if (changeCheckbox != false)
			$(caller).children('input[type=checkbox]').prop('checked', checked);

		trigger(getSettings(caller).onToggle, checked, caller);
	}

	function changeDisabled(caller, disabled, changeCheckbox) {
		if (disabled)
			$(caller).children('.NubWrapper').addClass('Disabled');
		else
			$(caller).children('.NubWrapper').removeClass('Disabled');

		if (changeCheckbox != false)
			$(caller).children('input[type=checkbox]').prop('disabled', disabled);
	}

	$.propHooks.checked = {
		set: function (el, value) {
			el.checked = value;
			$(el).trigger('change');
		}
	};

	$.propHooks.disabled = {
		set: function (el, value) {
			el.disabled = value;
			$(el).trigger('change');
		}
	};

	publicMethod.getSettings = function (internalElement) {
		return getSettings(internalElement);
	}

	$.fn.toggleCheckedState = function (value) {
		changeChecked($(this).closest(".ToggleSwitch"), value);
	}

	publicMethod.toggle = function (checked, caller) {
		changeChecked($(caller), checked);
	}

	function trigger(callback, value, caller) {
		if ($.isFunction(callback)) {
			callback.call(undefined, value, caller);
		}
	}
}(jQuery, document, window));