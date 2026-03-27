<style>
    .v-input { transition: border-color .2s, box-shadow .2s; }
    .v-input.is-valid { border-color: #10b981 !important; box-shadow: 0 0 0 1px #10b981; }
    .v-input.is-invalid { border-color: #ef4444 !important; box-shadow: 0 0 0 1px #ef4444; }
    .error-msg { display: block; min-height: 1.25rem; font-size: .75rem; color: #ef4444; margin-top: .25rem; transition: opacity .2s; }
    .password-strength { height: 4px; border-radius: 9999px; background: #e5e7eb; margin-top: .5rem; overflow: hidden; }
    .password-strength-bar { height: 100%; border-radius: 9999px; transition: width .3s, background-color .3s; width: 0; }
    .password-strength-label { font-size: .7rem; margin-top: .25rem; font-weight: 500; }
    .btn-loading { position: relative; pointer-events: none; opacity: .75; }
    .btn-loading .btn-text { visibility: hidden; }
    .btn-loading .btn-spinner { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
</style>

<script>
(function() {
    function validateCpfDigits(cpf) {
        var d = cpf.replace(/\D/g, '');
        if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) return false;
        for (var t = 9; t < 11; t++) {
            var sum = 0;
            for (var i = 0; i < t; i++) sum += parseInt(d[i]) * ((t + 1) - i);
            var digit = ((10 * sum) % 11) % 10;
            if (parseInt(d[t]) !== digit) return false;
        }
        return true;
    }

    function getErrorSpan(input) {
        var span = input.nextElementSibling;
        if (span && span.classList.contains('error-msg')) return span;
        var parent = input.parentElement;
        if (parent) {
            span = parent.querySelector('.error-msg');
            if (span) return span;
        }
        return null;
    }

    function validateField(input) {
        var type = input.dataset.validate;
        if (!type) return true;
        var val = input.value.trim();
        var span = getErrorSpan(input);
        var error = '';

        if (!val && input.hasAttribute('required')) {
            error = 'Campo obrigatório.';
        } else if (val) {
            switch (type) {
                case 'name':
                    if (val.length < 3) error = 'Mínimo de 3 caracteres.';
                    else if (!/\s/.test(val)) error = 'Informe nome e sobrenome.';
                    break;
                case 'email':
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) error = 'E-mail inválido.';
                    break;
                case 'cpf':
                    if (!/^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(val)) error = 'Use o formato 000.000.000-00.';
                    else if (!validateCpfDigits(val)) error = 'CPF inválido.';
                    break;
                case 'phone':
                    if (val.replace(/\D/g, '').length < 10) error = 'Mínimo de 10 dígitos.';
                    break;
                case 'password':
                    if (val.length < 8) error = 'Mínimo de 8 caracteres.';
                    updatePasswordStrength(input, val);
                    break;
                case 'password-confirm':
                    var pwField = input.form.querySelector('[data-validate="password"]');
                    if (pwField && val !== pwField.value) error = 'As senhas não conferem.';
                    break;
            }
        }

        input.classList.remove('is-valid', 'is-invalid');
        if (error) {
            input.classList.add('is-invalid');
            if (span) span.textContent = error;
        } else if (val) {
            input.classList.add('is-valid');
            if (span) span.textContent = '';
        } else {
            if (span) span.textContent = '';
        }

        return !error;
    }

    function updatePasswordStrength(input, val) {
        var container = input.closest('.field-group') || input.parentElement;
        var bar = container.querySelector('.password-strength-bar');
        var label = container.querySelector('.password-strength-label');
        if (!bar || !label) return;

        var score = 0;
        if (val.length >= 8) score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        var pct, color, text;
        if (score <= 1) { pct = '20%'; color = '#ef4444'; text = 'Fraca'; }
        else if (score <= 2) { pct = '40%'; color = '#f59e0b'; text = 'Razoável'; }
        else if (score <= 3) { pct = '65%'; color = '#3b82f6'; text = 'Boa'; }
        else { pct = '100%'; color = '#10b981'; text = 'Forte'; }

        if (!val) { pct = '0%'; text = ''; }

        bar.style.width = pct;
        bar.style.backgroundColor = color;
        label.textContent = text;
        label.style.color = color;
    }

    function applyMask(input) {
        var mask = input.dataset.mask;
        if (!mask) return;
        input.addEventListener('input', function() {
            var v = this.value.replace(/\D/g, '');
            switch (mask) {
                case 'cpf':
                    v = v.slice(0, 11);
                    if (v.length > 9) v = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6,9)+'-'+v.slice(9);
                    else if (v.length > 6) v = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6);
                    else if (v.length > 3) v = v.slice(0,3)+'.'+v.slice(3);
                    break;
                case 'phone':
                    v = v.slice(0, 11);
                    if (v.length > 6) v = '('+v.slice(0,2)+') '+v.slice(2,7)+'-'+v.slice(7);
                    else if (v.length > 2) v = '('+v.slice(0,2)+') '+v.slice(2);
                    else if (v.length > 0) v = '('+v;
                    break;
            }
            this.value = v;
        });
    }

    function initForm(form) {
        if (!form || form.dataset.vInit) return;
        form.dataset.vInit = '1';

        var inputs = form.querySelectorAll('.v-input[data-validate]');
        inputs.forEach(function(input) {
            applyMask(input);

            input.addEventListener('blur', function() { validateField(this); });
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) validateField(this);
                if (this.dataset.validate === 'password') {
                    updatePasswordStrength(this, this.value);
                    var confirm = this.form.querySelector('[data-validate="password-confirm"]');
                    if (confirm && confirm.value) validateField(confirm);
                }
            });
        });

        form.addEventListener('submit', function(e) {
            var allValid = true;
            var visibleInputs = form.querySelectorAll('.v-input[data-validate]');
            visibleInputs.forEach(function(input) {
                if (input.offsetParent !== null) {
                    if (!validateField(input)) allValid = false;
                }
            });

            if (!allValid) {
                e.preventDefault();
                var first = form.querySelector('.v-input.is-invalid');
                if (first) first.focus();
                return;
            }

            var btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.classList.contains('btn-loading')) {
                btn.classList.add('btn-loading');
                var text = btn.querySelector('.btn-text');
                if (!text) {
                    btn.innerHTML = '<span class="btn-text" style="visibility:hidden">' + btn.innerHTML + '</span><span class="btn-spinner"><svg class="w-5 h-5 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></span>';
                }
            }
        });
    }

    document.querySelectorAll('form[data-validate-form]').forEach(initForm);

    document.querySelectorAll('.v-input.is-invalid-init').forEach(function(input) {
        input.classList.add('is-invalid');
        input.classList.remove('is-invalid-init');
    });
})();
</script>
