/* Author: ragul-onemodo
Created: 2026-11-12 11:52:38 Asia/Calcutta (UTC+05:30) */
import Select from 'primevue/select';

export default {
    extends: Select,
    props: {
        required: Boolean,
    },
    methods: {
        onOptionSelect(event, option, isHide = true) {
            const activated = event?.type === 'mousedown' ||
                (event?.type === 'keydown' && ['Enter', 'NumpadEnter', 'Space'].includes(event.code));

            if (!this.required && isHide && activated && this.isSelected(option) && !this.isOptionDisabled(option)) {
                this.onClearClick(event);
                this.hide(true);
                return;
            }

            Select.methods.onOptionSelect.call(this, event, option, isHide);
        },
    },
};
