document.addEventListener('DOMContentLoaded', () => {
    // 1. Master System Notifications Toggle
    const masterToggle = document.getElementById('masterSystemNotifications');
    const sectionsToDisable = [
        document.getElementById('notificationMainGrid'),
        document.getElementById('modulePreferencesSection'),
        document.getElementById('deliverySettingsPanel'),
    ].filter(Boolean);

    const updateMasterState = () => {
        if (!masterToggle) return;
        const isEnabled = masterToggle.checked;
        sectionsToDisable.forEach(section => {
            if (isEnabled) {
                section.classList.remove('settings-section-disabled');
            } else {
                section.classList.add('settings-section-disabled');
            }
        });
        updateEventsCounter();
    };

    if (masterToggle) {
        masterToggle.addEventListener('change', updateMasterState);
        updateMasterState();
    }

    // 2. Module Master Toggles & Event Checkboxes
    const moduleCards = document.querySelectorAll('.module-preference-card');
    const counterBadge = document.getElementById('enabledEventsCount');

    const updateEventsCounter = () => {
        if (!counterBadge) return;

        if (masterToggle && !masterToggle.checked) {
            counterBadge.textContent = '0 Enabled Events';
            counterBadge.classList.add('zero-events');
            return;
        }

        let totalEnabled = 0;
        moduleCards.forEach(card => {
            const moduleSwitch = card.querySelector('.module-master-switch');
            const isModuleActive = !moduleSwitch || moduleSwitch.checked;
            if (isModuleActive) {
                const checkedEvents = card.querySelectorAll('.module-event-checkbox:checked');
                totalEnabled += checkedEvents.length;
            }
        });

        counterBadge.textContent = `${totalEnabled} Enabled Event${totalEnabled === 1 ? '' : 's'}`;
        if (totalEnabled === 0) {
            counterBadge.classList.add('zero-events');
        } else {
            counterBadge.classList.remove('zero-events');
        }
    };

    moduleCards.forEach(card => {
        const moduleSwitch = card.querySelector('.module-master-switch');
        const eventCheckboxes = card.querySelectorAll('.module-event-checkbox');

        if (moduleSwitch) {
            moduleSwitch.addEventListener('change', () => {
                if (moduleSwitch.checked) {
                    card.classList.remove('module-disabled');
                } else {
                    card.classList.add('module-disabled');
                }
                updateEventsCounter();
            });

            // Initial check
            if (!moduleSwitch.checked) {
                card.classList.add('module-disabled');
            }
        }

        eventCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateEventsCounter);
        });
    });

    updateEventsCounter();

    // 3. Conditional Daily Digest Time Dropdown
    const deliveryModeSelect = document.getElementById('delivery_mode');
    const digestTimeContainer = document.getElementById('digestTimeContainer');
    const digestTimeInput = document.getElementById('digest_time');
    const digestTimeHelper = document.getElementById('digestTimeHelper');

    const updateDeliveryModeState = () => {
        if (!deliveryModeSelect || !digestTimeContainer) return;

        const isDailyDigest = deliveryModeSelect.value === 'Daily Digest';
        if (isDailyDigest) {
            digestTimeContainer.classList.remove('is-disabled');
            if (digestTimeInput) digestTimeInput.disabled = false;
            if (digestTimeHelper) digestTimeHelper.textContent = 'Daily summary will dispatch at this designated time.';
        } else {
            digestTimeContainer.classList.add('is-disabled');
            if (digestTimeInput) digestTimeInput.disabled = true;
            if (digestTimeHelper) digestTimeHelper.textContent = 'Available only when Daily Digest is selected.';
        }
    };

    if (deliveryModeSelect) {
        deliveryModeSelect.addEventListener('change', updateDeliveryModeState);
        updateDeliveryModeState();
    }

    // 4. Quiet Hours Time Range Expansion
    const quietHoursToggle = document.getElementById('quietHoursToggle');
    const quietHoursRange = document.getElementById('quietHoursTimeRange');

    const updateQuietHoursState = () => {
        if (!quietHoursToggle || !quietHoursRange) return;

        if (quietHoursToggle.checked) {
            quietHoursRange.hidden = false;
            quietHoursRange.classList.add('is-open');
        } else {
            quietHoursRange.hidden = true;
            quietHoursRange.classList.remove('is-open');
        }
    };

    if (quietHoursToggle) {
        quietHoursToggle.addEventListener('change', updateQuietHoursState);
        updateQuietHoursState();
    }

    // 5. Save & Reset Action Buttons
    const saveButton = document.querySelector('.notification-save-bar .save-button');
    const resetButton = document.querySelector('.notification-save-bar .reset-button');

    if (saveButton) {
        saveButton.addEventListener('click', () => {
            if (typeof window.showSystemToast === 'function') {
                window.showSystemToast('Notification alert preferences saved successfully.', 'success', 'Saved');
            } else {
                alert('Notification alert preferences saved successfully.');
            }
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            const confirmed = window.confirm('Reset all notification preferences to system defaults?');
            if (!confirmed) return;

            // Reset Master
            if (masterToggle) masterToggle.checked = true;

            // Reset Channels
            document.querySelectorAll('.channel-row:not(.disabled) .switch input').forEach(input => {
                input.checked = true;
            });

            // Reset Priorities
            document.querySelectorAll('.priority-item .switch input').forEach(input => {
                input.checked = true;
            });

            // Reset Modules & Events
            moduleCards.forEach(card => {
                const moduleSwitch = card.querySelector('.module-master-switch');
                if (moduleSwitch) {
                    moduleSwitch.checked = true;
                    card.classList.remove('module-disabled');
                }
                const eventCheckboxes = card.querySelectorAll('.module-event-checkbox');
                eventCheckboxes.forEach((box, idx) => {
                    // Default to first 2 checked, 3rd optional based on initial state
                    box.checked = idx < 2;
                });
            });

            // Reset Schedule
            if (deliveryModeSelect) deliveryModeSelect.value = 'Immediate';
            if (digestTimeInput) digestTimeInput.value = '08:00';
            if (quietHoursToggle) quietHoursToggle.checked = false;

            updateMasterState();
            updateDeliveryModeState();
            updateQuietHoursState();
            updateEventsCounter();

            if (typeof window.showSystemToast === 'function') {
                window.showSystemToast('Notification settings have been restored to defaults.', 'info', 'Reset Complete');
            }
        });
    }
});
